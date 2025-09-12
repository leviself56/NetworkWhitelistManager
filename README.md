# NetworkWhitelistManager

### Requires:
* php
* composer
* routeros-api-php
* mikrotik

### Install dependency:
`composer require evilfreelancer/routeros-api-php`

### Description:
This utilizes a firewall rule (listed below) and an address list called "allowed_internet"

Simply add the following firewall rule to your filter and specify the out-interface of your WAN.

`/ip firewall filter add action=drop chain=forward comment="###NetworkWhitelist API###" disabled=yes out-interface=BR.WAN src-address-list=!allowed_internet`

Edit `config.php` to specify your MikroTik Router information.

Enable api service on the Router in IP -> Services
