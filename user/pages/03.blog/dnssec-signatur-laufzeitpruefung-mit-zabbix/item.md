---
title: Überprüfung des Ablaufdatums von DNSSEC gesicherten Zonen in Zabbix
date: 18:26 09/09/2015
taxonomy:
    category: blog
    tag: [dnssec,rrsig,laufzeit,zabbix]
continue_link: false
highlight:
    theme: solarized_dark
---

Wenn man eine über
[DNSSEC](https://de.wikipedia.org/wiki/Domain_Name_System_Security_Extensions)
gesicherte Zone betreibt, kenn man das Problem vieleicht: Man hat die
Zone signiert aber man vergisst nach den 30 Tagen eine neue Signatur
zu erzeugen.

Ich habe mir dafür inzwischen einen Cronjob der in regelmäßigen
Abständen die Zone neu generiert. Aber es ist doch sinnvoll über
Zabbix die Zonen zu überwachen das keine Domäne aus dem Raster fällt.

Ich habe dazu
[check-rrsig](https://github.com/datenknoten/check-rrsig) geschrieben,
welches das Ablaufdatum der Signatur überprüft. Das Skript verlangt
als Parameter einen Hostnamen, optional kann man noch einen anderen
Resolver als der aus `/etc/resolv.conf` angeben und Debugmeldungen
einschalten. Als erster Schritt wird zuerst der Hostname validiert,
dazu verwende ich die Bibliothek
[Respect\Validation](https://github.com/Respect/Validation). Danach
finde ich den Namensserver herraus der den Hostnamen zu Verfügung
stellt herraus und frage diesen, über die Bibliothek
[net_dns2](https://netdns2.com/), direkt ab, da ich etwaige Caches
umgehe, da ich ja einen möglichst realistischen Wert haben will. Dann
frage ich von dem Original den ersten `RRSIG`-Record ab und extrahiere
bei diesem den Ablaufzeitpunkt. Dann bilde ich noch den Unterschied
zwischen dem aktuellen Datum und dem Ablaufdatum, gebe es zurück und
bin fertig.

Die Integration in Zabbix ist damit relativ einfach. Man schafft die
phar-Datei auf dem Zabbix-Server nach `/etc/zabbix/externalscripts`
und kann dann ein Item entweder in einem Host oder in einem Template
über einen [External
Check](https://www.zabbix.com/documentation/2.4/manual/config/items/itemtypes/external)
das Skript abrufen und Werte erfassen.