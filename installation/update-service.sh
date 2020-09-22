#!/bin/bash

echo "Check if old service is running..."

systemctl is-active --quiet runasRootService > /dev/null 2>&1

if [ $? -ne 0 ]; then
    echo "Check if old service is installed..."

    systemctl list-unit-files | grep "runasRootService" > /dev/null 2>&1

    if [ $? -eq 0 ]; then 
        echo "Disabling old service..."

        systemctl disable runasRootService
    fi

    echo -n "Starting new service: "

    service app-runas-root start > /dev/null 2>&1

    if [ $? -eq 0 ]; then 
        echo "OK"
        exit 0
    else 
        echo "ERROR"
        exit 1
    fi
else
    echo ">>>  ERROR <<<  Could not complete installation !"
    echo "Please, check your system or ask support, before reboot."
    exit 1;
fi