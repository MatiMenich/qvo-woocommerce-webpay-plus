#!/bin/bash

echo -e "Please enter output plugin name: \c "
read release_name

if [ -f releases/$release_name.zip ]
  then
  rm releases/$release_name.zip
fi

zip -r releases/$release_name.zip . -x "releases/*" "assets-wp-repo/*" ".git/*" "generate_release.sh" ".gitignore" ".DS_Store"
