#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

WHEREAMI=`dirname $WHOAMI`
PROJECT=`dirname $WHEREAMI`

CONFIG=${PROJECT}/www/include/config.php
CONFIG_LOCAL=${PROJECT}/www/include/config-local.php

DBNAME='dotspotting'
USERNAME='dotspotting'

# We probably don't care about any errors...
PHP='php -d display_errors=off -q'

PASSWORD=`${PHP} ${PROJECT}/bin/generate_secret.php`

touch /tmp/${DNAME}.sql

echo "DROP DATABASE ${DBNAME};" >> /tmp/${DBNAME}.sql
echo "DROP user '${USERNAME}'@'localhost';" >> /tmp/${DBNAME}.sql
echo "FLUSH PRIVILEGES;" >> /tmp/${DBNAME}.sql

echo "CREATE DATABASE ${DBNAME};" >> /tmp/${DBNAME}.sql
echo "CREATE user '${USERNAME}'@'localhost' IDENTIFIED BY '${PASSWORD}';" >> /tmp/${DBNAME}.sql
echo "GRANT SELECT,UPDATE,DELETE,INSERT ON ${DBNAME}.* TO '${USERNAME}'@'localhost' IDENTIFIED BY '${PASSWORD}';" >> /tmp/${DBNAME}.sql
echo "FLUSH PRIVILEGES;" >> /tmp/${DBNAME}.sql

echo "USE ${DBNAME};" >> /tmp/${DBNAME}.sql;

for f in `ls -a ${PROJECT}/schema/*.schema`
do
	echo "" >> /tmp/${DBNAME}.sql
	cat $f >> /tmp/${DBNAME}.sql
done

mysql -u root -p < /tmp/${DBNAME}.sql
unlink /tmp/${DBNAME}.sql

echo "update the config file with db password"
perl -p -i -e "s/DB\-MAIN\-PASSWORD/${PASSWORD}/" ${CONFIG_LOCAL}

echo "done"
exit
