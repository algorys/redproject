# Plugin Redproject
This plugin display a roadmap and some other informations of your project in Dokuwiki :
* Name and Description of project
* Link to homepage define in Redmine
* Versions available (with creation and update date, total issues, open and closed)
* Members and Roles of the project
* Some different links to redmine, like the issues, subproject, mail of members,...
* Compatible with [Bootstrap](http://getbootstrap.com/) (need [Bootstrap3 Template](https://github.com/LotarProject/dokuwiki-template-bootstrap3/). 

Some of these features are not implemented yet and are still under development project. Be patience...
## Requirements
Redproject needs [Php-Redmine-API](https://github.com/kbsali/php-redmine-api) to work. Download it inside your shared folder of php, like ``/usr/share/php`` or in the redproject's folder. If you use [redissue](https://www.dokuwiki.org/plugin:redissue) the first option is better, as you have just to install it one time. 
```bash
$ mkdir vendor
$ cd vendor
$ git clone https://github.com/kbsali/php-redmine-api.git
$ cd php-redmine-api
$ git checkout v1.5.5
```

Don't forget to install the requirements of PhP-Redmine-API :
```bash
$ sudo apt-get install php5-curl php5-common
```

## Install
Download this plugin into your ``${dokuwiki_root}/lib/plugins`` folder and restart dokuwiki.

## Configuration
You can configure the plugin in the Config Manager of DokuWiki :

* redproject.url : Put your Redmine's url server, without a slash ending. Example : ``http://myredmine.com``
* redproject.API : Set your Redmine API's key, preference Administrator key.
* redproject.view : Choose the view you want to display. This will depend on the wiki user's access rights in Redmine.
  * Impersonate : select this if your wiki's users have the same UID as Redmine's users. e.g. : LDAP authentication. Redproject then will manage rights based on private or public projects.
  * Userview : doesn't manage access rights and display project even if it's in private project.

## Syntax
There is two way to use this plugin :

* First Syntax :

``<redproject proj="identifier_project" /> ``

* Second Syntax :

``<redproject proj="identifier_project">Text to display at the bottom of the page</redproject> ``

## Preview
Here is a preview of redproject :
![](http://s23.postimg.org/gtqfmo5rv/redproject.png)

For further information, see also [Redproject on dokuwiki.org](https://www.dokuwiki.org/plugin:redproject)

