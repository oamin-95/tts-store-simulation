#!/bin/bash

# Start Laravel Queue Worker for Kayan ERP Site Creation
# This worker processes background jobs for creating isolated ERPNext sites

cd /home/vboxuser/saas-marketplace

echo "Starting Queue Worker..."
php artisan queue:work --queue=default --tries=1 --timeout=600 --verbose
