#!/bin/sh

#
# Maintain a copy of the WordPress plugin repository checkout,
# for periodic manual synchronization between git and svn.
#

# halt on errors
set -e

WORKING=wp-whos-online.svn
REPO=http://plugins.svn.wordpress.org/wp-whos-online

if [ ! -f wp-whos-online.php ]; then
	echo "This must be run from the same directory as wp-whos-online.php"
	exit 1
fi

#
# set up the checkout directory
#

if [ ! -d "$WORKING" ]; then
	svn co "$REPO" --depth immediates "$WORKING"
	cd "$WORKING"

	svn up branches tags --set-depth immediates
	svn up trunk assets --set-depth infinity
else
	cd "$WORKING"
	svn up
fi

#
# copy in our current files
#

cd ..
cp -v *.php *.css *.js *.jpg LICENSE "$WORKING/trunk"
rsync -av --delete languages/ "$WORKING/trunk/languages/"
cp -v README.md "$WORKING/trunk/readme.txt"
cp -v assets/* "$WORKING/assets"

echo "\nsvn st $WORKING:\n-----------------------------"
svn st "$WORKING"
