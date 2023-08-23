ScriptsDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source "$ScriptsDir/Core_Common.sh"

Step=$1; Overide="$2"; AddVar1="$3" AddVar2="$4" AddVar3="$5" AddVar4="$6"


if [ "$Step" == "build" ]; then
	clear

	echo "GATHERING INFO FOR NEW VM"
	echo
	echo "# The {vm name} is what is used to execute commands against it. "
	echo "# For example, \"{vm name} dbsync\""
	echo "#"

	## VM Name
    Vars_GetVar "ENTERED_Guest_ShortNameSaved"
	while [ "$ENTERED_Guest_ShortName" == "" ]; do
		if [ "$ENTERED_Guest_ShortNameSaved" != "" ]; then Additional=" (enter for $ENTERED_Guest_ShortNameSaved): "; else Additional=": "; fi
		echo -ne "Please enter a name to use to access your VM$Additional"; 
		read ENTERED_Guest_ShortName
		if [ "$ENTERED_Guest_ShortName" == "" ] && [ "$ENTERED_Guest_ShortNameSaved" != "" ]; then
			ENTERED_Guest_ShortName=$ENTERED_Guest_ShortNameSaved
		fi
	done
	if [ -f "$BaseDir/scripts/UserConfig/$ENTERED_Guest_ShortName.sh" ]; then rm $BaseDir/scripts/UserConfig/$ENTERED_Guest_ShortName.sh; fi
    Vars_SaveVar "ENTERED_Guest_ShortNameSaved" $ENTERED_Guest_ShortName


	## IP
    Vars_GetVar "ENTERED_Address_GuestSaved"
	while [ "$ENTERED_Address_Guest" == "" ]; do
		if [ "$ENTERED_Address_GuestSaved" != "" ]; then Additional=" (enter for $ENTERED_Address_GuestSaved): "; else Additional=": "; fi
		echo -ne "What is the IP or Domain name of this machine$Additional"; 
		read ENTERED_Address_Guest

		if [ "$ENTERED_Address_Guest" == "" ] && [ "$ENTERED_Address_GuestSaved" != "" ]; then
			ENTERED_Address_Guest=$ENTERED_Address_GuestSaved
		fi
	done
    Vars_SaveVar "ENTERED_Address_GuestSaved" $ENTERED_Address_Guest


	## Create guest bootstrap
	echo "Guest_ShortName=\"$ENTERED_Guest_ShortName\"" > $BaseDir/scripts/UserConfig/$ENTERED_Guest_ShortName.sh
	echo "Address_Guest=\"$ENTERED_Address_Guest\"" >> $BaseDir/scripts/UserConfig/$ENTERED_Guest_ShortName.sh

	source $BaseDir/scripts/UserConfig/$ENTERED_Guest_ShortName.sh
	AddProfile "alias $Guest_ShortName=" "alias $Guest_ShortName='$BaseDir/scripts/ShellScripts/VM-Mgmt.sh --$ENTERED_Guest_ShortName'"
	####### VM CREATED


	## User password for host
    if [ "$Supreme" = true ]; then 
    	Vars_GetVar "Guest_PW"; 
    else 
    	Guest_PW=""; 
    	echo "An account for $Username will be created on the vm."; 
    fi 

	QuestionAddition=""
	while [ "$Guest_PW" == "" ]; do
		Question=""
		echo -ne "\r--- "$QuestionAddition"Please create a password for $Username "
		read -s ENTERED_Guest_PW1

		echo -ne "\r--- "$QuestionAddition"Please confirm the password for $Username "
		read -s ENTERED_Guest_PW2

		if [ "$ENTERED_Guest_PW1" == "$ENTERED_Guest_PW2" ]; then
			Guest_PW="$ENTERED_Guest_PW1";
			echo -ne "\r                                                                          ";
		else
			QuestionAddition="Passwords did not match. Try again. "
		fi
	done
    if [ "$Supreme" = true ]; then Vars_SaveVar "Guest_PW" $Guest_PW; fi
    

	### Sync scripts to guest as root
	echo; echo "SYNCING HOST SCRIPTS"
	$BaseDir/scripts/ShellScripts/VM-Mgmt.sh --$Address_Guest rkh
	{ 
		echo; ssh -o StrictHostKeyChecking=no root@$Address_Guest " echo beef "; echo;
	} &> $VM_Log;
	SyncScripts $Address_Guest root


	#######
	LocalKey=$(cat ~/.ssh/id_rsa.pub)
	ssh root@$Address_Guest "bash $BaseDir_RemoteRoot/scripts/ShellScripts/Guest_Build.sh 1 '' $Guest_PW \"$LocalKey\" $Username $AddVar1"


	#######
	SyncScripts $Address_Guest
	GuestUserKey=$(ssh $Username@$Address_Guest 'ssh-keygen -t rsa -N "" -f ~/.ssh/id_rsa >> /dev/null; cat ~/.ssh/id_rsa.pub')
	$BaseDir/scripts/ShellScripts/VM-Mgmt.sh --$Guest_ShortName rkh
	echo -e \"$GuestUserKey\" >> ~/.ssh/authorized_keys;
	{ ssh -o StrictHostKeyChecking=no $Username@$Address_Guest echo;} &> $VM_Log;

	
	#######
	if [ "$Supreme" = true ]; then $BaseDir/scripts/ShellScripts/VM-Mgmt.sh "mount"; fi

	echo; echo "You vm has been built and is ready to use."; echo; echo;
fi


if [ "$Step" == "1" ]; then CheckIfComplete "$Step" "$Overide";
	echo; echo "CREATING VM ACCOUNTS AND KEYS"
	#######

	VM_Log="/tmp/VMLog.txt"
	touch $VM_Log
	chmod -R 777 $VM_Log

	echo -n "--- Creating Accounts"
echo "Step $Step"
echo "Overide $Overide"
echo "AddVar1 $AddVar1"
echo "AddVar2 $AddVar2"
echo "AddVar3 $AddVar3"
echo "AddVar4 $AddVar4"

#	{
 		for group in wwwmgmt www wwwapache sambausers; do 
 		    groupadd $group;
 		done; 

		user='aquinn'
		useradd -g wwwmgmt $user
		usermod -aG www, wwwapache, sambausers $user

		echo "$AddVar1" | passwd $user --stdin
		echo "$user ALL=(ALL) NOPASSWD: ALL" >>  /etc/sudoers

		mkdir -p /home/$user/.ssh; 
		SetPerms $user wwwmgmt 700 /home/$user/.ssh false
		echo "$AddVar2" > /home/$user/.ssh/authorized_keys
		SetPerms $user wwwmgmt 600 /home/$user/.ssh/authorized_keys false

		echo -e 'export HISTTIMEFORMAT="%h %d %H:%M:%S "' >> /home/$user/.bashrc
		echo -e 'export HISTSIZE=10000' >> /home/$user/.bashrc
		echo -e 'export HISTFILESIZE=10000' >> /home/$user/.bashrc
		echo -e 'shopt -s histappend' >> /home/$user/.bashrc
		echo -e 'PROMPT_COMMAND="history -a"' >> /home/$user/.bashrc
		echo -e 'export HISTCONTROL=ignorespace:erasedups' >> /home/$user/.bashrc
		echo -e 'export HISTIGNORE="ls:ps:history"' >> /home/$user/.bashrc

		useradd -g wheel -G www, wwwapache, www, sambausers
#	} &> $VM_Log;
	echo "		100%"
#	CheckErrors "false"


	echo; echo "BUILDING SERVER"
	#######
	echo -n "--- Basic configuration"
	{
		hostnamectl set-hostname $Address_Guest
		sysctl -w vm.swappiness=0

		sed -i '/SELINUX=/d' /etc/selinux/config
		echo "SELINUX=disabled" >> /etc/selinux/config
		setenforce 0
	} &> $VM_Log; 
	echo "		100%"
	CheckErrors


	echo -n "--- Updating Yum"
	{
		yum update -y
		yum install -y wget
		yum install -y git
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing Apache"
	{
		yum install -y httpd
		ln -s /etc/httpd/ /usr/local/apache2
		systemctl enable httpd.service
		systemctl start httpd.service
		systemctl start firewalld
		firewall-cmd --permanent --zone=public --add-service=http
		firewall-cmd --permanent --zone=public --add-service=https
		firewall-cmd --reload
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing Mariadb"
	{
		#echo -e "[mariadb] \nname = MariaDB \nbaseurl = http://yum.mariadb.org/10.3.12/centos7-amd64 \ngpgkey=https://yum.mariadb.org/RPM-GPG-KEY-MariaDB \ngpgcheck=1 \n" > /etc/yum.repos.d/MariaDB.repo
		curl -sS https://downloads.mariadb.com/MariaDB/mariadb_repo_setup | sudo bash
		yum -y install MariaDB-server MariaDB-client
		systemctl enable mariadb
		systemctl start mariadb

		echo -e "\ny\ny\n$DBRootPass\n$DBRootPass\ny\ny\ny\ny" | mysql_secure_installation 2>/dev/null
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing PHP"
	{
		yum -y install https://dl.fedoraproject.org/pub/epel/epel-release-latest-7.noarch.rpm
		yum -y install https://rpms.remirepo.net/enterprise/remi-release-7.rpm

		yum -y install yum-utils
		yum-config-manager --enable remi-php74

		yum -y install yum-utils

		yum update
		yum -y install php php-cli

		yum install -y php php-pspell php-mysqlnd php-gd php-ldap php-pdo php-devel php-snmp php-soap php-common php-mbstring php-xmlrpc php-xml php-opcache php-imap php-mcrypt php-unzip python2-certbot-apache php-pecl-zip
		yum install -y php-pear gcc
		yum install -y libyaml*

		yum install -y ImageMagick ImageMagick-devel ImageMagick-perl
		echo -e "\n" | pecl install Imagick 2>/dev/null
		echo "extension=imagick.so" > /etc/php.d/imagick.ini

		echo -e "\n" | pecl install yaml 2>/dev/null
		echo "extension=yaml.so" > /etc/php.d/ext-yaml.ini

		systemctl restart httpd.service
		mkdir -p /var/log/php
		chown apache /var/log/php
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing SAMBA"
	{
		yum remove samba* -y
		yum install samba* -y
		yum install cifs-utils -y
		yum install nfs-utils -y

		firewall-cmd --permanent --add-port=137/tcp
		firewall-cmd --permanent --add-port=138/tcp
		firewall-cmd --permanent --add-port=139/tcp
		firewall-cmd --permanent --add-port=445/tcp
		firewall-cmd --permanent --add-port=901/tcp

		setsebool -P samba_enable_home_dirs on
		chcon -t samba_share_t /htdocs/

		# This is only the initial conf file
		# final config file is copied from SetupFiles/ConfigFiles/samba during the 
		SmbConf="[global] \nworkgroup = MYGROUP \nhosts allow = 127. 198.110.203. 192.168.0. \nmax protocol = SMB2 \nsecurity = user \nunix charset = UTF-8 \ndos charset = CP932 \nmap to guest = Bad User \n
			\nlog file = /var/log/samba/log.%m \nmax log size = 50 \n
			\n[homes] \nbrowsable = yes \nwritable = yes \nvalid users = %S \n"
		echo -e $SmbConf > /etc/samba/smb.conf

		echo "127.0.0.1 localhost" > /etc/samba/Imhosts
		chmod 644 /etc/samba/*

		systemctl enable smb
		systemctl start smb

		systemctl enable nmb
		systemctl start nmb

		echo -e "$GenericPasswordNotEncrypted\n$GenericPasswordNotEncrypted" | (smbpasswd -a $Username)
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing Webmin"
	{
		WebminRepo="[Webmin] \nname=Webmin Distribution Neutral \nbaseurl=http://download.webmin.com/download/yum \nmirrorlist=http://download.webmin.com/download/yum/mirrorlist \nenabled=1 \n"
		echo -e $WebminRepo > /etc/yum.repos.d/webmin.repo

		wget http://www.webmin.com/jcameron-key.asc
		rpm --import jcameron-key.asc
		yum update -y
		yum install webmin -y
		chkconfig webmin on

		sudo echo -e "$AddVar3:x::::::::::::" >> /etc/webmin/miniserv.users

		service webmin start
		firewall-cmd --permanent --add-port=10000/tcp
		firewall-cmd --reload
	} &> $VM_Log;
	echo "		100%"
	CheckErrors

	echo -n "--- Installing UNIX Tools"
	{
		# cronolog emulates charlie's apache logging
		# mod_ssl is needed for SSL to work in apache
		# drew said to ad sysstat
		# fail2ban stops SSH attacks
		# bind-utils provides the nslookup tool
		# whois provides the whois lookup for fail2ban

		yum -y install cronolog mod_ssl sysstat fail2ban bind-utils whois
		systemctl enable fail2ban
		ln -s /usr/sbin/cronolog /usr/local/sbin/cronolog

		### ADD MOD_PAGESPEED
		mkdir -p /tmp/pagespeed
		wget https://dl-ssl.google.com/dl/linux/direct/mod-pagespeed-stable_current_x86_64.rpm -O /tmp/pagespeed/mod-pagespeed-stable_current_x86_64.rpm
		yum install at -y
		rpm -U /tmp/pagespeed/mod-pagespeed-*.rpm
		chown www:wwwapache /var/cache/mod_pagespeed/
		chown www:wwwapache /var/log/pagespeed
	} &> $VM_Log;
	echo "	100%"
	CheckErrors


	echo -n "--- Installing Composer"
	{
		cd /usr/bin/

		php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
		php composer-setup.php
		php -r "unlink('composer-setup.php');"

		ln -s composer.phar composer
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing Certbot"
	{
		mkdir -p /etc/letsencrypt /var/lib/letsencrypt /var/log/letsencrypt;
		chown www /etc/letsencrypt /var/lib/letsencrypt /var/log/letsencrypt;
		chgrp www //etc/letsencrypt /var/lib/letsencrypt /var/log/letsencrypt;
		chmod 777 /etc/letsencrypt /var/lib/letsencrypt /var/log/letsencrypt;

		yum-config-manager --enable rhui-REGION-rhel-server-extras rhui-REGION-rhel-server-optional
		yum -y install certbot-apache
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing Inotify"
	{
        yum install -y inotify-tools
	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Installing ftps"
	{
		yum install -y vsftpd
		systemctl start vsftpd
		systemctl enable vsftpd
		firewall-cmd --zone=public --permanent --add-port=21/tcp
		firewall-cmd --zone=public --permanent --add-service=ftp
		#firewall-cmd –-reload
		cp /etc/vsftpd/vsftpd.conf /etc/vsftpd/vsftpd.conf.default

		if grep -Fq "anonymous_enable" /etc/vsftpd/vsftpd.conf; then sed -i s/anonymous_enable=YES/anonymous_enable=NO/g /etc/vsftpd/vsftpd.conf; else echo -e anonymous_enable=NO >> /etc/vsftpd/vsftpd.conf; fi
		if grep -Fq "local_enable" /etc/vsftpd/vsftpd.conf; then sed -i s/local_enable=NO/local_enable=YES/g /etc/vsftpd/vsftpd.conf; else echo -e local_enable=YES >> /etc/vsftpd/vsftpd.conf; fi
		if grep -Fq "write_enable" /etc/vsftpd/vsftpd.conf; then sed -i s/write_enable=NO/write_enable=YES/g /etc/vsftpd/vsftpd.conf; else echo -e write_enable=YES >> /etc/vsftpd/vsftpd.conf; fi
		if grep -Fq "userlist_enable" /etc/vsftpd/vsftpd.conf; then sed -i s/userlist_enable=NO/userlist_enable=YES/g /etc/vsftpd/vsftpd.conf; else echo -e userlist_enable=YES >> /etc/vsftpd/vsftpd.conf; fi
		if grep -Fq "userlist_file" /etc/vsftpd/vsftpd.conf; then sed -i s|userlist_file.*|userlist_file=/etc/vsftpd/user_list|g /etc/vsftpd/vsftpd.conf; else echo -e userlist_file=/etc/vsftpd/user_list >> /etc/vsftpd/vsftpd.conf; fi
		if grep -Fq "userlist_deny" /etc/vsftpd/vsftpd.conf; then sed -i s/userlist_deny=YES/userlist_deny=NO/g /etc/vsftpd/vsftpd.conf; else echo -e userlist_deny=NO >> /etc/vsftpd/vsftpd.conf; fi

		systemctl restart vsftpd

	} &> $VM_Log;
	echo "		100%"
	CheckErrors


	echo -n "--- Creating crontab services"
	{
		crontab -l > ~/mycron &> /dev/null
		echo "@reboot ( sleep 5 ; sh $BaseDir/scripts/ShellScripts/Guest_ConfigureMounts.sh )" >> ~/mycron
		echo "0      0     1       *       *     $BaseDir/scripts/scripts/ShellScripts/Guest_CertBotRenew.sh" >> ~/mycron
		crontab ~/mycron
		rm ~/mycron
	} &> $VM_Log;
	echo "	100%"
	CheckErrors
fi


if [ "$Step" == "2" ]; then CheckIfComplete "$Step" "$Overide";
	Address_Guest="$AddVar1";

	echo -n "--- Installing phpMyAdmin"
	{
		cd /htdocs;
		rm -rf /htdocs/phpmyadmin/*
		rm -rf /htdocs/phpmyadmin/.*

		composer update
		composer create-project phpmyadmin/phpmyadmin
		sudo chown -R www /var/lib/php/session/
	} &> $VM_Log;
	echo "	done"
	CheckErrors


	#######
	echo -n "--- Configuring git"
	{
		git config --global user.email "$Username@nmu.edu"; git config --global user.name "$Username"; git config --global push.default simple;
	} &> $VM_Log;
	echo "		done"
	CheckErrors



HERE: Composer update above?
	if [ "$AddVar4" == "nmu" ]; then
		#######
		echo -n "--- Composer Install"
		{
			composer install
		} &> $VM_Log;
		echo "		done"
		CheckErrors
	fi


	if [ "$SOMETHING" == "add_drupal" ]; then
		#######
		echo -n "--- Installing Drush"
		{
			cd /htdocs;
			composer require drush/drush
			echo -e "y\n" | "/htdocs/Drupal/vendor/drush/drush/drush init" 2>/dev/null
			chmod a+rx /htdocs/Drupal/vendor/drush/drush/drush /htdocs/Drupal/vendor/drush/drush/drush.php
		} &> $VM_Log;
		echo "		done"
		CheckErrors


		#######
		echo -n "--- Config symlinks & profile"
		{
			if [ -d $HomeDir/bin ]; then rm -rf $HomeDir/bin; fi

			HomeDir=/home/$Username
			mkdir -p $HomeDir/bin
			chmod 777 $HomeDir/bin
			cd $HomeDir/bin

			if [ ! -f drupal ]; then ln -s /htdocs/Drupal/vendor/drupal/console/bin/drupal drupal; fi
			if [ ! -f drush ]; then ln -s /htdocs/Drupal/vendor/drush/drush/drush drush; fi
			if [ ! -f drush.php ]; then ln -s /htdocs/Drupal/vendor/drush/drush/drush.php drush.php; fi
			if [ ! -f php-parse ]; then ln -s /htdocs/Drupal/vendor/nikic/php-parser/bin/php-parse php-parse; fi
			if [ ! -f phpunit ]; then ln -s /htdocs/Drupal/vendor/phpunit/phpunit/phpunit phpunit; fi
			if [ ! -f psysh ]; then ln -s /htdocs/Drupal/vendor/psy/psysh/bin/psysh psysh; fi
			cd /
			chgrp users $HomeDir/bin
			chown $Username $HomeDir/bin

			AddProfile "" "if [ -f ~/.bashrc ]; then . ~/.bashrc; fi"		"# .bashrc include" "$Username"
			AddProfile "" "PATH=\"\$HOME/bin:\$HOME/.local/bin:\$PATH\""	"" "$Username"
			AddProfile "" "PS1='\u@\h: \w\\\$ '" "# setup your default cursor" "$Username"
			AddProfile "" "export PATH PS1" "" "$Username"
			AddProfile "" "alias perms=\"find . -type d -exec sudo chmod 775 '{}' \;; find . -type f -exec sudo chmod 664 '{}' \;\"" "" "$Username"
			AddProfile "" "" "" "$Username"

			if [ "$Supreme" = true ]; then
				AddProfile "" "alias cms='cd /htdocs/cmsphp/'" "" "$Username"
				AddProfile "" "alias theme='cd /htdocs/Drupal/sites/all/themes/zen_nmu/'" "" "$Username"
				AddProfile "" "alias ls='ls -l | more'" "" "$Username"
				AddProfile "" "alias pids='ps aux | grep -i aquinn'" "" "$Username"
				AddProfile "" "alias src='source ~/.bash_profile'" "" "$Username"
			fi

			source ~/.bash_profile
		} &> $VM_Log;
		echo "	done"
		CheckErrors
	fi


	#######
	echo -n "--- Creating SSL Keys"
	{
		sudo systemctl restart httpd

		sudo mkdir -p /usr/local/apache2/htdocs; sudo chmod 777 /usr/local/apache2/htdocs; sudo chgrp wwwmgmt /usr/local/apache2/htdocs;
	    echo "<html><body><h1>$Address_Guest works!</h1></body></html>" > /usr/local/apache2/htdocs/index.html

		if [ -d $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest ]; then
			if [ -d /etc/letsencrypt ]; then sudo rm -rf /etc/letsencrypt; fi
			sudo cp -r $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/letsencrypt /etc
		else
			sudo chmod 777 /usr/local/apache2/conf/httpd.conf
			echo -e "\n<VirtualHost *:80>\n\tServerName nmu.edu\n\tRewriteRule \"^/.well-known/acme-challenge\" - [L]\n</VirtualHost>" >> /usr/local/apache2/conf/httpd.conf
			sudo chmod 644 /usr/local/apache2/conf/httpd.conf

			sudo systemctl restart httpd
			sudo certbot --apache --agree-tos --non-interactive --email $Username@nmu.edu --domains $Address_Guest  
			

			sudo mkdir -p $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/; sudo chmod -R 777 $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/;
			sudo cp -r /etc/letsencrypt $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/

			sudo mkdir -p $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/conf; sudo chmod -R 777 $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/conf;
			sudo cp /etc/httpd/conf/httpd-le-ssl.conf $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/conf/
		fi

		HTTPSync $Username $Address_Guest 'step2'

		sudo systemctl restart httpd
	} &> $VM_Log;
	echo "		done"
	CheckErrors


	#######
	echo -n "--- Configuring other services"
	{
		if [ -f /etc/php.ini ]; then sudo rm -rf /etc/php.ini; fi; sudo cp $BaseDir/SetupFiles/ConfigFiles/php/php.ini /etc;
		if [ -f /etc/my.cnf ]; then sudo rm -rf /etc/my.cnf; fi; sudo cp $BaseDir/SetupFiles/ConfigFiles/mariadb/my.cnf /etc;
		if [ -f /etc/samba/smb.cnf ]; then sudo rm -rf /etc/samba/smb.cnf; fi; sudo cp $BaseDir/SetupFiles/ConfigFiles/samba/smb.conf /etc/samba;
		if [ -f /etc/fail2ban/jail.local ]; then sudo rm -rf /etc/fail2ban/jail.local; fi; sudo cp $BaseDir/SetupFiles/ConfigFiles/fail2ban/jail.local /etc/fail2ban;
		sudo chmod 644 /etc/php.ini /etc/my.cnf /etc/samba/smb.conf; sudo chmod 775 /etc/fail2ban;

		sudo sed -i "s/.*verbose.*/\$cfg['Servers'][\$i]['verbose'] = '$Address_Guest';/g" /htdocs/phpmyadmin/config.inc.php
		sudo chmod 664 /htdocs/phpmyadmin/config.inc.php

		find /usr/local/apache2/conf/nmu/ -type d -exec sudo chmod 775 '{}' \;
		find /usr/local/apache2/conf/nmu/ -type f -exec sudo chmod 664 '{}' \;
	} &> $VM_Log;
	echo "	done"
	CheckErrors


	#######
	echo -n "--- Restarting services"
	{
		sudo systemctl restart httpd
		sudo systemctl restart mariadb
		sudo systemctl restart sshd
	} &> $VM_Log;
	CheckErrors
	echo "		done"


	#######
	echo -n "--- Performing cleanup"
	{
		if [ ! -f /usr/bin/bash ]; then sudo ln -s /usr/bin/bash /usr/local/bin/bash; fi

		yum clean all
		sudo rm -rf /tmp/*
		sudo rm -f /var/log/wtmp /var/log/btmp
		history -c
		history -w
	} &> $VM_Log;
	echo "		done"
	CheckErrors
fi




