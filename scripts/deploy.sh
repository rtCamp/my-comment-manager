#!/usr/bin/env bash
# On push of git tag push specified files to SVN repo.
# Author: Utkarsh Patel (@utkarsh_me)
# Credits:
# - https://github.com/xwp/wp-dev-lib
# - https://deliciousbrains.com/deploying-wordpress-plugins-travis/

if [[ -z "$TRAVIS" ]]; then
	echo "Script is only to be run by Travis CI" 1>&2
	exit 1
fi

if [ -z "$TRAVIS_TAG" ]; then
	echo "Build should only take place on tag push." 1>&2
	exit 0
fi

if [ "$WP_MULTISITE" -ne "0" ]; then
	echo "Skip building, We will only build on WP_MULTISITE=0." 1>&2
	exit 0
fi

if [[ -z "$WP_ORG_PASSWORD" ]]; then
	echo "WordPress.org password not set" 1>&2
	exit 1
fi

if [[ -z "$WP_ORG_USERNAME" ]]; then
	echo "WordPress.org username not set'" 1>&2
	exit 1
fi

if [[ -z "$PLUGIN" ]]; then
	echo "WordPress.org plugin slug not set'" 1>&2
	exit 1
fi

plugin_dir="$(pwd)"
cd ..
build_main_dir="$(pwd)"
svn_tmp_repo_dir="$build_main_dir/svn"
cd $plugin_dir
svn_url="https://plugins.svn.wordpress.org/$PLUGIN"
# Check for plugin version
for php in *.php; do
	if grep -q 'Plugin Name:' $php && grep -q 'Version:' $php; then
		plugin_version=$(cat $php | grep 'Version:' | sed 's/.*Version: *//')
	fi
done

if [ -z "$plugin_version" ]; then
	echo "Unable to find plugin version"
	exit 1
fi

if ! grep -q "$plugin_version" readme.txt && [ $TRAVIS_TAG == $plugin_version ];then
	echo "Please update readme.txt to include $plugin_version in changelog"
	exit 1
fi

# Check if the tag exists for the version we are building
TAG=$(svn ls "$svn_url/tags/$plugin_version")
error=$?
if [ $error == 0 ]; then
    # Tag exists, don't deploy
    echo "Tag already exists for version $plugin_version, aborting deployment"
    exit 1
fi

# Install composer dependency
if [ -f composer.json ]; then
	composer install --no-dev
fi

# SVN Update
svn checkout $svn_url $svn_tmp_repo_dir

cd $build_main_dir

# Move out the trunk directory to a temp location
mv svn/trunk ./svn-trunk

# Create trunk directory
mkdir svn/trunk

# Copy files to trunk - exclude list scripts/exclude-files
rsync -avz --delete --delete-excluded \
	--exclude-from="$plugin_dir/scripts/exclude-files" \
	$plugin_dir/ $svn_tmp_repo_dir/trunk/

# Copy assets assuming svn assets folder will not have any other subfolder.
if [ -e $plugin_dir/assets/ ]; then
	if [ -e $svn_tmp_repo_dir/assets/.svn ]; then
		# Copy svn
		asset_has_svn=1
		mv $svn_tmp_repo_dir/assets/.svn $svn_tmp_repo_dir/../svn-assets
	fi
	mkdir -p $svn_tmp_repo_dir/assets/
	rsync -avz --delete --include='*.jpg' --include='*.jpeg' --include='*.png' --include='*.svg' --exclude='*' --delete-excluded $plugin_dir/assets/ $svn_tmp_repo_dir/assets/
	if [ -n "$asset_has_svn" ]; then
		# Restore svn.
		mv $svn_tmp_repo_dir/../svn-assets $svn_tmp_repo_dir/assets/.svn
	fi
fi

# Copy all the .svn folders from the checked out copy of trunk to the new trunk.
# This is necessary as the Travis container runs Subversion 1.6 which has .svn dirs in every sub dir
cd svn/trunk/
TARGET=$(pwd)
cd ../../svn-trunk/

# Find all .svn dirs in sub dirs
SVN_DIRS=`find . -type d -iname .svn`

for SVN_DIR in $SVN_DIRS; do
    SOURCE_DIR=${SVN_DIR/.}
    TARGET_DIR=$TARGET${SOURCE_DIR/.svn}
    TARGET_SVN_DIR=$TARGET${SVN_DIR/.}
    if [ -d "$TARGET_DIR" ]; then
        # Copy the .svn directory to trunk dir
        cp -r $SVN_DIR $TARGET_SVN_DIR
    fi
done

# Back to builds dir
cd ../

# Remove checked out dir
rm -fR svn-trunk

# Add new version tag
mkdir svn/tags/$plugin_version
rsync -avz --delete --delete-excluded \
	--exclude-from="$plugin_dir/scripts/exclude-files" \
	$plugin_dir/ svn/tags/$plugin_version

# Add new files to SVN
svn stat svn | grep '^?' | awk '{print $2}' | xargs -I x svn add x@
# Remove deleted files from SVN
svn stat svn | grep '^!' | awk '{print $2}' | xargs -I x svn rm --force x@
svn stat svn

# Commit to SVN
svn ci --no-auth-cache --username $WP_ORG_USERNAME --password $WP_ORG_PASSWORD svn -m "Deploy version $plugin_version"
# Remove SVN temp dir
rm -rf svn