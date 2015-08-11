---
title: Postfix mit Zabbix überwachen
date: 11:15 08/11/2015
taxonomy:
    category: blog
    tag: [zabbix,postfix,überwachung]
continue_link: false
---

Hinweis: Dies ist eine Übersetzung ins deutsche von [Zabbix: Postfix statistical graphs using passive checks](http://lifeisabug.com/postfix-statistical-graphs-zabbix-using-passive-checks/). Zusätzlich habe ich das Zabbix-Template und das Skript hier noch gespiegelt.

Diese Anleitung hilft bei der Erstellung von passiven Checks. Es gibt dazu ein Skript welches mit [logtail](http://www.fourmilab.ch/webtools/logtail/) und [pflogsumm](http://jimsun.linxnet.com/postfix_contrib.html) die Postfix Log-Dateien auswertet und in eine Statistik-Datei schreibt. Zum Schluss wird ein `UserParameter` erstellt, welcher die Daten aus der Statistik-Datei an Zabbix weitergibt.

### Vorraussetzungen installieren

Wir müssen pflogsumm und logtail installieren. Bei [Debian](https://www.debian.org/) sieht das so aus:

```bash
$ apt-get install pflogsumm logtail
```
### Skript einrichten

Das [Skript](postfix-zabbix-stats.bash) muss als `postfix-zabbix-stats.bash` in `/usr/local/bin` gespeichert werden und als ausführbar (`+x`) makiert werden.

Danach legt man einen Cronjob für das Skript an, damit es die Statistik-Datei schreiben kann:

```
*/5 * * * * /usr/local/bin/postfix-zabbix-stats.bash
```

### Zabbix Agent einrichten

In der Datei `/etc/zabbix/zabbix_agentd.conf` muss man den Wert `EnableRemoteCommands` auf 1 setzen.

Danach erstellt man eine Datei `/etc/zabbix/zabbix_agentd.conf.d/postfix.conf` mit folgendem Inhalt:

```bash
UserParameter=postfix.pfmailq,mailq | grep -v "Mail queue is empty" | grep -c '^[0-9A-Z]'
UserParameter=postfix[*],/usr/local/bin/postfix-zabbix-stats.bash $1
```

Agent neustarten und in diesem Kapitel fertig.

### Zabbix Template importieren

Nun muss man das [Template](smtp_and_postfix_passive_checks_zabbix_template.xml) importieren und dem Host auf dem Postfix läuft zuweisen und schon ist man fertig und bekommt nette Postfix-Statistiken.