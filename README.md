## PHP script to auto update your lets encrypt certs from your SiteGround host to maxCDN

#Getting Started

1. Create an API key on MaxCDN
    - https://cp.maxcdn.com/account/api/create

2. White list the ip address of your site ground
    - https://cp.maxcdn.com/account/api/whitelist

3. Create a public folder on you SiteGround cpanel under /public_html
    ~~~
   cd ~/public_html
   mkdir [created_folder_name]
    ~~~

4. Pull the script into the folder on you SiteGround server
    ~~~
   composer require gitchrisqueen/siteground_maxcdn_letsencrypt_autoupdate
    ~~~

5. Update your API credentials
    - Inside /public_html/created_folder/vendor/gitchrisqueen/siteground_maxcdn_letsencrypt_autoupdate/src/autoupdate.php
    - Update lines 18-20
    ~~~PHP
   const CONSUMER_KEY = 'xxxxxx'; // Consumer Key from MaxCDN
   const CONSUMER_SECRET = 'XXXX'; // Consumer Secret from MaxCDN;
   const ALIAS = 'XXXXXX'; // Alias from MaxCDN 
   ~~~

6. Setup a cron tab that calls /public_html/[created_folder_name]/index.php
    - Recommended: Run a daily cron
