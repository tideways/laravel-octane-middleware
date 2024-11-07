#!/bin/bash

echo 'deb [signed-by=/usr/share/keyrings/tideways.gpg] https://packages.tideways.com/apt-packages-main any-version main' | sudo tee /etc/apt/sources.list.d/tideways.list > /dev/null
wget -qO - 'https://packages.tideways.com/key.gpg' | gpg --dearmor | sudo tee /usr/share/keyrings/tideways.gpg > /dev/null
sudo apt-get update
sudo apt-get install tideways-php
