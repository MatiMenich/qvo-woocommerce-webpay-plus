#!/bin/bash

echo -e "Please enter output apk name: \c "
read release_name

if [ -f releases/$release_name.zip ]
  then
  rm releases/$release_name.apk
fi

zip -r releases/$release_name.zip src/
