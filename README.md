# NetworkWhitelistManager

### Requires:
* php
* composer
* routeros-api-php
* mikrotik

### Install dependency:
`composer require evilfreelancer/routeros-api-php`

### Description:
NetworkWhitelist Manager is a web based DHCP client whitelist management tool to allow specific devices access to the internet. The default firewall rule blocks all traffic which is not listed on the MikroTik address list "allowed_internet". Clients set to "Allowed" are added to the address list and have full access to the internet. All others are labled as "Blocked" and can only access other devices on the local network. Since this whitelist is managed via IP Address, the clients should be set to "Static" by the DHCP Server (this option is available in the web interface tool), so the allowed device does not acquire a different IP address. 

Comments can be updated in the web interface to notate device information. The "System Active" / "System Disabled"  button at the top activates or disables the main firewall drop rule. You will need to add one rule (listed below) to your firewall, enable `api` access in IP -> Services and edit `config.php` to allow the software to connect to your MikroTik router for API commands.

Index.php is secured via a password (listed in index.php) to prevent unauthorized access to the web tool.

Simply add the following firewall rule to your filter and specify the out-interface of your WAN.

`/ip firewall filter add action=drop chain=forward comment="###NetworkWhitelist API###" disabled=yes out-interface=BR.WAN src-address-list=!allowed_internet`
