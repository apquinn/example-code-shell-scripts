#!/usr/local/bin/bash
FileAction=$1

if [ "$FileAction" == "unlock" ]; then
	chmod g+w /htdocs/Drupal/.htaccess; chmod g+w /htdocs/Drupal/sites/sites.php; chmod g+w /htdocs/Drupal/sites/; chmod g+w /htdocs/Drupal/robots.txt;
	echo; echo "The files have been unlocked"; echo;
elif [ "$FileAction" == "lock" ]; then
	chmod g-w /htdocs/Drupal/.htaccess; chmod g-w /htdocs/Drupal/sites/sites.php; chmod g-w /htdocs/Drupal/sites/; chmod g-w /htdocs/Drupal/robots.txt
	echo; echo "The files have been locked"; echo;
fi
