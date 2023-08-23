export TERM=xterm
ScriptsDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
source "$ScriptsDir/Core_Common.sh"

Username=$1
Address_Guest=$2


systemctl stop httpd
rm -rf /usr/local/apache2/conf
cp -r $BaseDir/SetupFiles/ConfigFiles/apache/Frank/conf /usr/local/apache2/
cd /usr/local/apache2/conf/

grep -RiIl 'ServerAdmin' | xargs sed -i "s|ServerAdmin .*|ServerAdmin $Username@nmu.edu|g"
grep -RiIl 'ServerName' | xargs sed -i "s|nmu.college|$Address_Guest|g"
grep -RiIl 'ServerName' | xargs sed -i "s|Listen.*:|Listen $Address_Guest:|g"

rm httpd.ORGINAL

SSLCertificateFile=$(grep -hr "SSLCertificateFile" $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/conf/httpd-le-ssl.conf)
grep -RiIl 'SSLCertificateFile' | xargs sed -i "s|SSLCertificateFile .*|$SSLCertificateFile|g"

SSLCertificateKeyFile=$(grep -hr "SSLCertificateKeyFile" $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/conf/httpd-le-ssl.conf)
grep -RiIl 'SSLCertificateKeyFile' | xargs sed -i "s|SSLCertificateKeyFile .*|$SSLCertificateKeyFile|g"

SSLCertificateChainFile=$(grep -hr "SSLCertificateChainFile" $BaseDir/SetupFiles/ConfigFiles/LetsEncrypt/$Address_Guest/conf/httpd-le-ssl.conf)
grep -RiIl 'SSLCertificateChainFile' | xargs sed -i "s|SSLCertificateChainFile .*|$SSLCertificateChainFile|g"




