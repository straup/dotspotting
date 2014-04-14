#!/bin/sh

WHOAMI=`python -c 'import os, sys; print os.path.realpath(sys.argv[1])' $0`

WHEREAMI=`dirname $WHOAMI`
PROJECT=`dirname $WHEREAMI`

CONFIG=${PROJECT}/www/include/config.php
CONFIG_LOCAL=${PROJECT}/www/include/config-local.php

# We probably don't care about any errors...
PHP='php -d display_errors=off -q'

COOKIE_SECRET=`${PHP} ${PROJECT}/bin/generate_secret.php`
CRUMB_SECRET=`${PHP} ${PROJECT}/bin/generate_secret.php`
PASSWORD_SECRET=`${PHP} ${PROJECT}/bin/generate_secret.php`

# If someone can figure out the nightmare of escaping all this stuff
# for sed I would gladly accept the patches (20120523/straup)

perl -p -i -e "s/CRYPTO\-COOKIE\-SECRET/${COOKIE_SECRET}/" ${CONFIG_LOCAL}
perl -p -i -e "s/CRYPTO\-CRUMB\-SECRET/${CRUMB_SECRET}/" ${CONFIG_LOCAL}
perl -p -i -e "s/CRYPTO\-PASSWORD\-SECRET/${PASSWORD_SECRET}/" ${CONFIG_LOCAL}
