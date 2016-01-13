---
title: DNSSEC mit Bind & OVH
date: 19:32 04/08/2015
taxonomy:
    category: blog
    tag: [dnssec,bind,ovh,tech,linux,krautspace]
continue_link: false
highlight:
    theme: solarized_dark
---

Ich habe gestern im [Krautspace](https://www.krautspace.de/) ein paar Takte zu [DNS](http://en.wikipedia.org/wiki/Domain_Name_System) im Allgemeinen und [DNSSEC](http://en.wikipedia.org/wiki/Domain_Name_System_Security_Extensions) im Speziellen erzählt.

Ziel des Abends war es, die [Schibboleth](http://en.wikipedia.org/wiki/Shibboleth) vorzustellen, die es braucht um DNSSEC mit Bind und OVH zum laufen zu bekommen und diese will ich hier nochmal für die Nachwelt hinterlassen. Ich habe das ganze für die Domain „kaoskinder.de“ gemacht.

Zurest erzeugt man einen „zone signing key“:

```bash
dnssec-keygen -a RSASHA512 -b 4096 -n ZONE kaoskinder.de
```

Danach brauchts noch einen „key signing key“:

```bash
dnssec-keygen -f KSK -a RSASHA512 -b 4096 -n ZONE kaoskinder.de
```

Diese Befehle erzeugen 4 Dateien:

* Kkaoskinder.de.+010+11091.key
* Kkaoskinder.de.+010+11091.private
* Kkaoskinder.de.+010+13430.key
* Kkaoskinder.de.+010+13430.private

Die key-Dateien enthalten die öffentlichen Schlüssel und die private-Dateien aus offensichtlichen Gründen die privaten Schlüssel.

Als nächstes inkludiert man die key-Dateien in die Zone-Datei:

```dns
$INCLUDE Kkaoskinder.de.+010+11091.key
$INCLUDE Kkaoskinder.de.+010+13430.key
```

Danach muss man die zone-Datei unterschreiben:

```bash
dnssec-signzone -A -3 $(head -c 1000 /dev/random | sha1sum | cut -b 1-16) -N INCREMENT -o kaoskinder.de -t db.kaoskinder.de.zone
```

Diesen Schritt muss man alle 30 Tage wiederholen, da dann die Signaturen auslaufen.

Der letzte Schritt erzeugt eine unterschriebene Zonen-Datei „db.kaoskinder.de.zone.signed“, diese muss dann noch in die bind-Konfiguration eintragen.

Jetzt kann man z.B. mit dem [Verisign DNSSEC Debugger](http://dnssec-debugger.verisignlabs.com/) schonmal testen ob DNSSEC funktioniert. Es wird noch eine Fehlermeldung kommen, das kein DS-Record in der übergeordneten Zone existiert. Dieser Fehler wird in dem nächsten Schritt behoben, in dem wir unseren öffentlichen Schlüssel bei [OVH](http://www.ovh.de/) eintragen.

Dazu loggt man sich im [alten OVH Interface](https://www.ovh.de/managerv3/login.pl?xsldoc=&domain=&time=&language=de&csid=&ticketId=&level=) ein. Danach wählt man oben die gewünschte Domain, wählt im linken Menü „Domain & DNS“, dann rechts oben „Sichere Delegation (DNSSEC)“. Dort klickt man auf „Änderung“.

Jetzt sucht man sich eine der beiden key-Dateien aus, ich habe jetzt die Datei „Kkaoskinder.de.+010+11091.key“ ausgewählt. Die Datei sieht wie folgt aus:

```dns
; This is a key-signing key, keyid 11091, for kaoskinder.de.
; Created: 20150407191914 (Tue Apr  7 21:19:14 2015)
; Publish: 20150407191914 (Tue Apr  7 21:19:14 2015)
; Activate: 20150407191914 (Tue Apr  7 21:19:14 2015)
kaoskinder.de. IN DNSKEY 257 3 10 AwEAAZ5v3RLmjVMcjEodqam6IXkkG9NQp3G88hddDY1VClGtIsJtgU42 6t61fDrKoHFRn607lbn06OkCre9fWBophP4xTt9sX877yNb1LRtOpLAS lEYY8p4w6OiDv3CMoyT6oO7j+L3g3puYc+57NmFa4hzWFrEF4RuVis4b argcPudoTISwA+/DB3C5UNwOQB5WsnSEXd4krVO/49Gs2FIOCj3/4Ja6 g/v3x0R3axkLZV1PnawYlDVpAI0qI3xXhxlzZvT64GI+HYQds3Im+Bvs aMO1S224xm/99v0TKwSLfPenX3DW0VpRY5efvUgVUu8zl6HaEQolLLmu ZaKVe9kEn/9mzDX30SkBtNNc0athdNDRofd710n86SnybDpn5K0qME7W qcW6n53voAaObv1yR3dmvFsVeu2dRhYHHqOzMH94JnqixsjTAGH80DKR ZjMEK666Va1jgBY928XPRx3zH8thQe+FrOK4Ad/kihZYwi9kovKeGBdl VVZDoI/CaRjdhSzpBShyXakNhNWtSo/qs7QN4TjxDdN9TYPKLSToIc2m mzvG/u5saTh/oTDSkP9Xh3bOceFKAV5iJJDVo5oDEUYNCyQL5YvcYJ1R tD2Fb1mzIrvPyOq5q3MDDhTjPEqBqiiVYwDKJ4eMy81AuxLUG4+Bekbc iprdIfcp3HdR6QAZ
```

Der Wert nach keyid trägt man bei Kennung ein, die Zahl nach DNSKEY wählt man bei Flags aus. Als Algorithmus wählt man 10. Bei „öffentlicher Schlüssel“ trägt man den ganzen Kram hinter der 10 ein, also von „AwEAAZ5v“ bis „HdR6QAZ“. Dann klickt man auf „Bestätigen“ und wartet auf die Erfolgsmeldung.

Jetzt da die Zone per DNSSEC gesichert ist, kann man sich auch den schönen Sachen wie [DANE](http://en.wikipedia.org/wiki/DNS-based_Authentication_of_Named_Entities) oder [SSHFP](http://de.wikipedia.org/wiki/SSHFP_Resource_Record) hinwenden.