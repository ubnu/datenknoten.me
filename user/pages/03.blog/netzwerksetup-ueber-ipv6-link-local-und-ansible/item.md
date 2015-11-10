---
title: Netzwerksetup über IPv6 Link Local und ansible
date: 20:55 11/10/2015
taxonomy:
    category: blog
    tag: [rezept,ipv6,link local,ansible]
highlight:
    theme: solarized_dark    
---

Folgende Problemstellung sei gegeben:

Man hat ein Image für eine Debian-Instalation, das man auf einem Rechner (Dabei ist es egal ob echt oder virtuel) aufgespielt hat, kein Netzwerk definiert, man kennt aber die [MAC-Adresse](https://de.wikipedia.org/wiki/MAC-Adresse) des Netzwerk-Interfaces und will jetzt das Netzwerk konfigurieren.
 
Die Lösung ist relativ einfach, da man über die MAC-Adresse die [IPv6-Link-Local-Adresse](https://de.wikipedia.org/wiki/IPv6#Link-Local-Adressen) berechnen kann, mit der man sich dann zum sshd des Rechners verbinden kann.
 
Ich zeige nun wie ich das ganze über [Ansible](http://www.ansible.com/) gelöst habe.

Meine Ordnerstruktur für mein Ansible-Setup sieht so aus:

```
. ansible.cfg
. hosts
/ host_vars
|--> . xmpp.int.datenknoten.me
/ playbooks
|--> . network.yml
|--> / filter_plugins
|----> . conv_mac2ll.py
/ templates
|--> network
|----> . interfaces
|----> . resolv.conf
|----> . sysctl.conf
```

Nun werde ich die einzelnen Dateien erklären was sie tun.

Die Datei `hosts` sieht für dieses Beispiel so aus:

```
[linux_guests]
xmpp.int.datenknoten.me
```

In dem Ordner `host_vars` gibt es für jeden Host eine Datei. Für den Rechner xmpp.int.datenknoten.me sieht die Datei so aus:

```
---
id: 4
mac: 52:54:00:b8:e9:a1
```

Das Feld `id` enthält eine Zahl die für die Berechnung der IPv4 NAT Adresse und der globalen IPv6 Adresse benutzt wird. Das Feld `mac` ist die MAC-Adresse des Rechners. Diese Adresse wird für die Berechnung der IPv6-Link-Local-Adresse benötigt.

Die Datei `conv_mac2ll.py` enthält ein Python-Skript das die eigentliche Berechnung vornimmt:
 
```
#!/usr/local/bin/python

def mac2ll(mac):
    mac = mac.split(":")
    mac.insert(3,'fe')
    mac.insert(3,'ff')
    mac[0] = str(int(mac[0]) ^ 6)
    return "fe80::%s:%s:%s:%s" % ("".join(mac[0:2]),"".join(mac[2:4]),"".join(mac[4:6]),"".join(mac[6:8]))


class FilterModule(object):
    ''' Ansible network jinja2 filters '''
    def filters(self):
        return {
            'mac2ll': mac2ll
            }
```

Mein eigentliches Playbook für den Netzwerkkram ist dadurch sehr übersichtlich:

```
---
- hosts: linux_guests
  vars:
    ansible_ssh_host: "{{ mac|mac2ll }}%vtnet0"
  tasks:
    - name: write sysctl.conf to the disk
      copy: src=../templates/network/sysctl.conf dest=/etc/sysctl.conf
    - name: write resolv.conf to the disk
      copy: src=../templates/network/resolv.conf dest=/etc/resolv.conf
    - name: write /etc/network/interfaces
      template: src=../templates/network/interfaces dest=/etc/network/interfaces
    - name: restart networking
      shell: "/sbin/ifdown eth0; /sbin/ifup eth0"

```

Da ich die Playbooks auf einer FreeBSD Kiste ausführe heist das Netzwerk-Interface hier `vtnet0`. Unter Linux heist dieses aller Wahrscheinlichkeit nach `eth0`.

In der `sysctl.conf` deaktiviere ich das Router Advertisement, da ich alles hart verdrahte:
 
```
net.ipv6.conf.all.accept_ra=0
net.ipv6.conf.all.autoconf=0
```

Namensserver Konfiguration ist jetzt auch nicht weltbewegend:

```
nameserver 192.168.122.9
search int.datenknoten.me
```

Und zum Schluss das wichtigste, die Netzwerkkonfiguration:
 
```
auto lo
iface lo inet loopback

# The primary network interface
auto  eth0
iface eth0 inet static
  address   192.168.122.{{ id }}
  broadcast 192.168.122.255
  netmask   255.255.255.0
  gateway   192.168.122.1

iface eth0 inet6 static
  address 2a01:4f8:200:2265:3:100::{{ id }}
  netmask 112
  gateway fe80::5054:ff:fe9f:c3e4
```