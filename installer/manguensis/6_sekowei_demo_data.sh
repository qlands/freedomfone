#!/bin/bash
#Script to add demo data

SVNROOT=/usr/local/freedomfone

/usr/bin/mysql -u root -p freedomfone < $SVNROOT/gui/app/install/freedomfone_demo_data.sql

DEMODIR=/usr/local/freedomfone/installer/sekowei/demo

cp $DEMODIR/ivr_nodes/* $SVNROOT/gui/app/webroot/freedomfone/ivr/nodes/
cp $DEMODIR/ivr_ivr/*.mp3   $SVNROOT/gui/app/webroot/freedomfone/ivr/100/ivr/
cp $DEMODIR/ivr_ivr/*.wav   $SVNROOT/gui/app/webroot/freedomfone/ivr/100/ivr/
cp $DEMODIR/ivr_ivr/ivr_full.xml   $SVNROOT/gui/app/webroot/xml_curl/ivr.xml
cp $DEMODIR/ivr_ivr/ivr.xml   $SVNROOT/gui/app/webroot/freedomfone/ivr/100/conf/
cp $DEMODIR/lam_ivr/*   $SVNROOT/gui/app/webroot/freedomfone/leave_message/100/audio_menu/
cp $DEMODIR/lam_inbox/* $SVNROOT/gui/app/webroot/freedomfone/leave_message/100/messages/
bash $SVNROOT/extras/fix_perms_gui.sh
bash $SVNROOT/extras/fix_perms_fs.sh

