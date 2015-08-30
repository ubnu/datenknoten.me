---
title: Mein VPN-Setup
date: 09:53 08/30/2015
taxonomy:
    category: blog
    tag: [linux,openvpn,internet,ipv6,dnssec,ipsec]
continue_link: false
---

Ziel dieses Artikel ist es zu zeigen wie mein [OpenVPN](https://openvpn.net/)-Setup
funktioniert, wie ich meine Clients konfiguriere und was das für
Vorteile bringt.

Fangen wir mal mit dem warum an. Mein initiales anliegen war in das
virtuele Netzwerk meines [libvirt](https://libvirt.org/)/[KVM](http://www.linux-kvm.org/page/Main_Page)-Hostes einzusteigen,
damit ich die virtuellen Maschienen besser verwalten kann. Ich habe
zuerst an [IPSec](https://de.wikipedia.org/wiki/IPsec) versucht, bin da aber an der harschen
Internet-Realität gescheitert. So hat mein Internet-Anbieter Kabel
Deutschland fragmentierte UDP-Pakete klammheimlich verworfen. Hat mit
mich einige Zeit gekostet das raus zu finden, aber dank
[Netalyzr](http://netalyzr.icsi.berkeley.edu/) findet man solche
Sachen dann doch relativ schnell. An dieser Stelle muss ich mal eine
Lanze für Netalyzr brechen, wenn ihr in ein neues Netz kommt, führt
den Test einmal aus, um euch der Limitationen des Netzes bewust zu
werden. Dann habe ich mich an OpenVPN versucht und alles funktionierte
ohne mit der Wimper zu zucken.

Bevor ich jetzt zu der Konfiguration komme, noch ein paar Takte zu
meiner Infrastruktur.

### Infrastruktur

Ich habe einen
[Server](https://www.hetzner.de/hosting/produkte_rootserver/ex40) bei
Hetzner gemieted auf dem ich mehrere virtuelle Maschienen mit
unterschiedichen [Diensten](/dienste). Da ich nur eine IPv4-Addresse
habe und aus kostengründen nichts ändern will, habe ich ein privates
LAN mit NAT für die ganzen virtuellen Rechner eingerichtet. Das
IPv4-Lan hat den Prefix `192.168.122.0/24`, dieser Prefix sollte
natürlich auch über das VPN erreichbar sein. Daneben habe ich von
Hetzner einen nativen IPv6 Zugang erhalten und habe den Prefix
`2a01:4f8:200:2265::/64`. Was im Verlauf des einrichtens gelernt habe
ist, das wenn man verschiedene Unterinfrastrukturen hat, diese auch
mit eigenen Prefixen beglücken. So habe ich den /64 in mehrere
/112-Netze aufgeteilt. Die Virtuellen Server haben den Prefix
`2a01:4f8:200:2265:3::/112` und das VPN-Netz hat
`2a01:4f8:200:2265:4::/112`. Was ich in diesem Kontext auch gelernt
habe, das `2000::/3` der komplette öffentlich routbare Teil von IPv6
ist. Zum Schluss sei noch gesagt das ich einen
[LDAP](https://de.wikipedia.org/wiki/Lightweight_Directory_Access_Protocol)-Verzeichnis
betreibe in dem ich Benutzer verwalte, das sollte auch an das OpenVPN
angebunden werden. Soviel zur Infrastruktur, jetzt zur Konfiguration.

### Server Konfiguration

Meine Server Konfiguration sieht so aus:

```
# Crypto konfigurieren
ca /etc/ipsec.d/cacerts/cacert.pem
cert /etc/ipsec.d/certs/node2.datenknoten.me.pem
key /etc/ipsec.d/private/node2.datenknoten.me.pem
dh /etc/ipsec.d/dh4096.pem

# Netzwerk aufsetzen
server 10.8.0.0 255.255.255.0
server-ipv6 2a01:4f8:200:2265:4::1/112
push "route 192.168.122.0 255.255.255.0"
push "redirect-gateway def1 bypass-dhcp"
push "route-ipv6 2000::/3"
push "dhcp-option DNS 192.168.122.9"

# Einstellungen
keepalive 10 120
comp-lzo
persist-key
persist-tun
verb 3
cipher AES-256-CBC
port 1194
proto tcp
dev tun

# LDAP aktivieren
plugin /usr/lib/openvpn/openvpn-auth-ldap.so /etc/openvpn/auth-ldap.conf
```


Die LDAP konfiguration sieht so aus:

```
<LDAP>
        URL             ldaps://ldap.datenknoten.me
        BindDN          cn=systemuser,ou=users,dc=datenknoten,dc=me
        Password        tolles passwort
        Timeout         15
        TLSEnable       no
        FollowReferrals yes
</LDAP>

<Authorization>
        BaseDN          "ou=users,dc=datenknoten,dc=me"
        SearchFilter    "(uid=%u)"
        RequireGroup    false
        <Group>
                BaseDN          "ou=groups,dc=datenknoten,dc=me"
                SearchFilter    "cn=vpnusers"
                MemberAttribute memberUid
        </Group>
</Authorization>

```

Hier ist anzufügen das die Limitierung auf die Gruppe `vpnusers`
irgendwie nie geklapt hat. Für Sachdienliche Hinweise wäre ich sehr
dankbar.

Als nächstes muss auf dem Server noch die Firewall eingerichtet
werden. Ich benutze das Programm
„[Ferm](http://ferm.foo-projects.org/)” um meine [IPTables](http://www.netfilter.org/projects/iptables/)-Regeln zu
verwalten. Entsprechend sieht mein Script so aus:

```
# -*- shell-script -*-
#
#  Configuration file for ferm(1).
#

@def $DEV_WORLD = eth0;
@def $DEV_DMZ = virbr1;

@def $HOST_STATIC = 144.76.154.114;


@def $DEV_PRIVATE = virbr1;
@def $NET_PRIVATE = 192.168.122.0/24;

@def $DEV_VPN = tun0;
@def $NET_VPN = 10.8.0.0/24;

# convenience function which creates both the nat/DNAT and the filter/FORWARD
# rule
@def &FORWARD_TCP($proto, $port, $dest) = {
    # interface (lo $DEV_WORLD $DEV_VPN $DEV_PRIVATE)
    table filter chain FORWARD outerface $DEV_DMZ daddr $dest proto $proto dport $port ACCEPT;
    table nat chain PREROUTING daddr $HOST_STATIC proto $proto dport $port DNAT to $dest;
}

table filter {
    chain INPUT {
        policy ACCEPT;

        mod state state INVALID DROP;
        mod state state (ESTABLISHED RELATED) ACCEPT;

        interface $DEV_DMZ proto (tcp udp) dport (53 67) ACCEPT;
    }
    chain OUTPUT {
        policy ACCEPT;
    }
    chain FORWARD {
        policy ACCEPT;
        mod state state INVALID DROP;
        mod state state (ESTABLISHED RELATED) ACCEPT;
        interface $DEV_PRIVATE ACCEPT;
        interface $DEV_VPN mod conntrack ctstate NEW ACCEPT;
        mod conntrack ctstate (ESTABLISHED RELATED) ACCEPT;
    }
}

table nat {
    chain POSTROUTING {
        # masquerade private IP addresses
        saddr ($NET_PRIVATE $NET_VPN) outerface $DEV_WORLD MASQUERADE;
    }
}

domain ip6 {
    table filter {
        chain INPUT {
            policy ACCEPT;
        }
        chain OUTPUT {
            policy ACCEPT;
        }
        chain FORWARD {
            policy ACCEPT;
            interface ($DEV_WORLD $DEV_VPN) outerface $DEV_DMZ daddr 2a01:4f8:200:2265::/64 ACCEPT;
            outerface ($DEV_WORLD $DEV_VPN) interface $DEV_DMZ saddr 2a01:4f8:200:2265::/64 ACCEPT;
            interface $DEV_DMZ outerface $DEV_DMZ ACCEPT;
            interface ($DEV_WORLD $DEV_VPN) outerface $DEV_DMZ REJECT reject-with icmp6-port-unreachable;
            outerface ($DEV_WORLD $DEV_VPN) interface $DEV_DMZ REJECT reject-with icmp6-port-unreachable;

        }
    }
}

&FORWARD_TCP(tcp, (80 443), 192.168.122.2);

```

Es empfiehlt sich natürlich etwas mit der Materie auseinander zu
setzen damit man versteht was ich hier schreibe. Vieles was in diesen
Konfigurations-Dateien steht hat sich über die Jahre so entwickelt.

### Client Konfiguration (Linux)

Unter Linux ist bis auf einen Punkt eigentlich alles sehr entspannt. Das Problem ist, das der DNS-Server den ich bereitstelle nicht übernommen wird. Dafür gibt es eine Lösung und jetzt erstmal die Config:

```
client
dev tun
proto tcp
remote 144.76.154.114
resolv-retry infinite
nobind
persist-key
persist-tun
ca dk-ca.crt
cert manjaro.crt
key manjaro.key
verb 3
cipher AES-256-CBC
auth SHA1
reneg-sec 0
route-delay 4
comp-lzo no
auth-user-pass
script-security 2
up /home/hana/openvpn/datenknoten/update-dns
down /home/hana/openvpn/datenknoten/update-dns
```

2 Anmerkungen: Zum einen sei hier der eintrag `auth-user-pass`
hervorzuheben, der den Client auffordert sich Zugangsdaten vom
Benutzer zu erfragen. Zum anderen die letzten 3 Zeile. Diese sorgen
nämlich mittels einem kleinen Skript das
[openresolv](http://roy.marples.name/projects/openresolv/index)
aufruft, den mitgelieferte DNS-Server in der Datei `/etc/resolv.conf`
einträgt. Hier das Skript:

```
#!/bin/bash
#
# Parses DHCP options from openvpn to update resolv.conf
# To use set as 'up' and 'down' script in your openvpn *.conf:
# up /etc/openvpn/update-resolv-conf
# down /etc/openvpn/update-resolv-conf
#
# Used snippets of resolvconf script by Thomas Hood <jdthood@yahoo.co.uk>
# and Chris Hanson
# Licensed under the GNU GPL.  See /usr/share/common-licenses/GPL.
# 07/2013 colin@daedrum.net Fixed intet name
# 05/2006 chlauber@bnc.ch
#
# Example envs set from openvpn:
# foreign_option_1='dhcp-option DNS 193.43.27.132'
# foreign_option_2='dhcp-option DNS 193.43.27.133'
# foreign_option_3='dhcp-option DOMAIN be.bnc.ch'
# foreign_option_4='dhcp-option DOMAIN-SEARCH bnc.local'

## You might need to set the path manually here, i.e.
RESOLVCONF=/sbin/resolvconf

case $script_type in

up)
  for optionname in ${!foreign_option_*} ; do
    option="${!optionname}"
    echo $option
    part1=$(echo "$option" | cut -d " " -f 1)
    if [ "$part1" == "dhcp-option" ] ; then
      part2=$(echo "$option" | cut -d " " -f 2)
      part3=$(echo "$option" | cut -d " " -f 3)
      if [ "$part2" == "DNS" ] ; then
        IF_DNS_NAMESERVERS="$IF_DNS_NAMESERVERS $part3"
      fi
      if [[ "$part2" == "DOMAIN" || "$part2" == "DOMAIN-SEARCH" ]] ; then
        IF_DNS_SEARCH="$IF_DNS_SEARCH $part3"
      fi
    fi
  done
  R=""
  if [ "$IF_DNS_SEARCH" ]; then
    R="search "
    for DS in $IF_DNS_SEARCH ; do
      R="${R} $DS"
    done
  R="${R}
"
  fi

  for NS in $IF_DNS_NAMESERVERS ; do
    R="${R}nameserver $NS
"
  done
  #echo -n "$R" | $RESOLVCONF -p -a "${dev}"
  echo -n "$R" | $RESOLVCONF -a "${dev}.inet"
  ;;
down)
  $RESOLVCONF -d "${dev}.inet"
  ;;
esac
```

### Client Konfiguration (Android)

Unter Android benutze ich [OpenVPN for
android](http://ics-openvpn.blinkt.de/), welches es auch im [F-Droid
Store](https://f-droid.org/repository/browse/?fdfilter=openvpn&fdid=de.blinkt.openvpn)
gibt.

Das Einrichten ist Einfach und es gibt nichts zu beachten. Als ich das
VPN eingerichtet habe, habe ich einen Bug in dem von mir benutzen
XMPP-Client [Conversations](http://conversations.im/) gefunden, weil
dieser, bzw die darunterliegende DNS-Bibliothek die DNS-Server nicht
richtig bestimmen
[konnte](https://github.com/rtreffer/minidns/issues/12) (Ist
inzwischen behoben).

### Fazit

Insgesamt bin ich mit dem Setup sehr zufrieden, vorallem weil es
kaputte Netze brauchbar macht, da ich durch das VPN ein zensurfreies
Netz, IPv6, einen DNSSEC fähigen Resolver bekomme. Bei Fragen könnt
ihr mich entweder per [E-Mail](mailto:tim@datenknoten.me) oder auch im
[Chat](https://www.krautspace.de/chat2/) des
[Krautspaces](https://www.krautspace.de/) kontaktieren.