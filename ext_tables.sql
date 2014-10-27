CREATE TABLE sys_domain (
	tx_awesome_url_domain int(11) unsigned DEFAULT '0' NOT NULL,
);

CREATE TABLE pages (
	tx_awesome_url_alias varchar(40) DEFAULT '',
	tx_awesome_url_exclude_sub tinyint(4) DEFAULT '0' NOT NULL,
);

CREATE TABLE pages_language_overlay (
	tx_awesome_url_alias varchar(40) DEFAULT '',
	tx_awesome_url_exclude_sub tinyint(4) DEFAULT '0' NOT NULL,
);

CREATE TABLE tx_awesome_url_domain (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,

	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,

	sorting int(10) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,

	uid_foreign int(11) DEFAULT '0' NOT NULL,

	sys_language_uid int(11) DEFAULT '-1' NOT NULL,
	path_prefix varchar(40) DEFAULT NULL,

	PRIMARY KEY (uid),
);

CREATE TABLE tx_awesome_url_uri (
	uid int(11) NOT NULL auto_increment,

	domain_name varchar(80) NOT NULL,
	uri varchar(255) NOT NULL,
	uri_depth int(1) NOT NULL,
	status int(1) DEFAULT '1' NOT NULL,
	uid_foreign int(11) NOT NULL,
	sys_language_uid_foreign int(11) NOT NULL,

	PRIMARY KEY (uid),
	KEY tx_awesome_url_domain_idx1 (domain_name, uri, uri_depth, status),
	UNIQUE KEY tx_awesome_url_domain_idx2 (domain_name, uri),
	KEY tx_awesome_url_domain_idx3 (status, uid_foreign, sys_language_uid_foreign),
);
