#!/bin/bash

SESSION_LOCATION=/var/lib/php/session/

echo ""
echo "*****"
echo ""
echo "changing owner of $SESSION_LOCATION and all contents to 'www'"
sudo chown -R www ${SESSION_LOCATION}
echo "ownership changed."
echo ""
sleep 1

echo "make sure apache didn't create a default ssl.conf file in /conf.d/"
echo ""

SSLFile=/usr/local/apache2/conf.d/ssl.conf
SSLFileMoved=/usr/local/apache2/conf.d/ssl.BAK

if [[ -f ${SSLFileMoved} ]]
then
  if [[ -f ${SSLFile} ]]
    then
      sudo rm -rf ${SSLFileMoved}
      echo "previous ssl backup file has been removed"
      sudo mv ${SSLFile} ${SSLFileMoved}
      echo "ssl file has been moved, apache should now restart"
  else
    echo "no ssl file found in /conf.d/."
  fi
fi
sleep 1


Drupal7File=/htdocs/Drupal/sites/default/settings.php

if [[ -f ${Drupal7File} ]]
then
  echo ""
  echo "adjusting local drupal session time to six hours"
  sudo sed -i "s/'session.gc_maxlifetime', 3600/'session.gc_maxlifetime', 21600/g" ${Drupal7File}
else
  echo "Drupal 8 sessions cannot be auto adjusted"
fi
sleep 1

echo ""
echo "All processes complete"
echo ""
echo "*****"
echo ""



# find this: ini_set('session.gc_maxlifetime', 3600);
# replace with: ini_set('session.gc_maxlifetime', 21600);
# this will allow local sessions to last 6 hours in drupal on dev machines
# can be found in /Drupal/sites/default/settings.php
