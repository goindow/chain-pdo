#!/bin/bash

php=/usr/local/bin/php

for case in *.php; do
  php $case;
done 