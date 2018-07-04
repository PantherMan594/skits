#!/bin/bash

java -jar /var/www/yuicompressor-2.4.8.jar --type css style.css > style.min.css
java -jar /var/www/yuicompressor-2.4.8.jar --type css skits.css > skits.min.css
java -jar /var/www/yuicompressor-2.4.8.jar --type css font-awesome.css > font-awesome.min.css
