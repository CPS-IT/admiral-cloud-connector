#
# Table structure for table 'sys_file'
#
CREATE TABLE sys_file (
	tx_admiralcloudconnector_linkhash varchar(255) DEFAULT '' NOT NULL,
	tx_admiralcloudconnector_identifier_before_migration TEXT
);

#
# Table structure for table 'sys_file_reference'
#
CREATE TABLE sys_file_reference (
	tx_admiralcloudconnector_crop text
);

#
# Table structure for table 'be_users'
#
CREATE TABLE be_users (
	first_name varchar(50) DEFAULT '' NOT NULL,
	last_name varchar(50) DEFAULT '' NOT NULL,
	security_group varchar(50) DEFAULT '' NOT NULL
);

CREATE TABLE tx_admiralcloudconnector_security_groups (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted tinyint(4) DEFAULT '0' NOT NULL,
	hidden tinyint(4) DEFAULT '0' NOT NULL,
	starttime int(11) DEFAULT '0' NOT NULL,
	endtime int(11) DEFAULT '0' NOT NULL,
	sorting int(11) DEFAULT '0' NOT NULL,
	ac_security_group_id int(11) DEFAULT '0' NOT NULL,
	be_groups varchar(100) DEFAULT '0' NOT NULL,
	PRIMARY KEY (uid),
	KEY parent (pid)
);
