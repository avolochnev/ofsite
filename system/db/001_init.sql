-- initialize gfbrd database
CREATE TABLE `gf_access` (
  `realm_id` varchar(15) NOT NULL default '',
  `user_id` int(10) unsigned NOT NULL default '0',
  `till` datetime default NULL,
  PRIMARY KEY  (`realm_id`,`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gf_nick_change` (
  `change_id` int(10) unsigned NOT NULL auto_increment,
  `prev` int(10) unsigned NOT NULL default '0',
  `next` int(10) unsigned NOT NULL default '0',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  PRIMARY KEY  (`change_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gf_realm` (
  `realm_id` varchar(15) NOT NULL default '',
  `description` varchar(250) NOT NULL default '',
  `url` varchar(250) default NULL,
  `is_restrict` enum('Y','N') NOT NULL default 'N',
  PRIMARY KEY  (`realm_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gf_user` (
  `user_id` int(10) unsigned NOT NULL auto_increment,
  `nick` varchar(50) NOT NULL default '',
  `nickid` varchar(150) NOT NULL default '',
  `password` varchar(50) NOT NULL default '',
  `last_name` varchar(50) default NULL,
  `first_name` varchar(50) default NULL,
  `middle_name` varchar(50) default NULL,
  `birth` date default NULL,
  `email` varchar(250) NOT NULL default '',
  `secret` varchar(50) NOT NULL default '',
  `icq` varchar(20) default NULL,
  `note` text,
  `reg_date` date default NULL,
  `active` enum('Y','N') NOT NULL default 'Y',
  `session` varchar(100) default NULL,
  `last_enter` bigint(20) unsigned default NULL,
  `hide_email` enum('Y','N') NOT NULL default 'N',
  `url` varchar(250) default NULL,
  `realm_id` varchar(15) default NULL,
  `open_mailbox` enum('Y','N') NOT NULL default 'N',
  `picture_url` varchar(250) default NULL,
  PRIMARY KEY  (`user_id`),
  UNIQUE KEY `nickid` (`nickid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_black_list` (
  `user_id` int(10) unsigned NOT NULL default '0',
  `black_id` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`user_id`,`black_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


CREATE TABLE `gfb_block` (
  `block_id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) unsigned NOT NULL default '0',
  `message_id` int(10) unsigned default NULL,
  `rule_id` int(10) unsigned default NULL,
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `till` datetime default NULL,
  `created_by` int(10) unsigned default NULL,
  `comment` text,
  PRIMARY KEY  (`block_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_book` (
  `book_id` smallint(5) unsigned NOT NULL auto_increment,
  `book_name` varchar(100) NOT NULL default '',
  `about` text,
  `description` mediumtext,
  `priority` tinyint(3) unsigned NOT NULL default '50',
  `access_rights` varchar(250) default NULL,
  `create_discussion` varchar(250) default NULL,
  `pseudo_book` enum('Y','N') NOT NULL default 'N',
  `admin_rights` varchar(250) default NULL,
  `alive_term` tinyint(3) unsigned NOT NULL default '7',
  `archived` date default NULL,
  `spec_message` text,
  PRIMARY KEY  (`book_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_discussion` (
  `discussion_id` int(10) unsigned NOT NULL auto_increment,
  `book_id` smallint(5) unsigned NOT NULL default '1',
  `userid` int(10) unsigned NOT NULL default '0',
  `caption` varchar(100) NOT NULL default '',
  `date` datetime default NULL,
  `deleted_by` int(10) unsigned NOT NULL default '0',
  `is_archived` enum('Y','N') NOT NULL default 'N',
  `dont_archive` enum('Y','N') NOT NULL default 'N',
  `last_time` int(10) unsigned default NULL,
  `first_time` int(10) unsigned default NULL,
  `voting` varchar(50) default NULL,
  PRIMARY KEY  (`discussion_id`),
  KEY `deleted_by` (`deleted_by`,`is_archived`),
  KEY `date` (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_ip_block` (
  `ip_block_id` int(10) unsigned NOT NULL auto_increment,
  `ip` varchar(30) NOT NULL,
  `comment` text,
  `rule_id` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`ip_block_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_message` (
  `message_id` int(10) unsigned NOT NULL auto_increment,
  `discussion_id` int(10) unsigned NOT NULL default '0',
  `userid` int(10) unsigned NOT NULL default '0',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `comment` varchar(50) default NULL,
  `text` text,
  `deleted_by` int(10) unsigned NOT NULL default '0',
  `time` int(10) unsigned NOT NULL default '0',
  `ip` varchar(30) default NULL,
  PRIMARY KEY  (`message_id`),
  KEY `discussion_id` (`discussion_id`),
  KEY `userid` (`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_preferences` (
  `user_id` int(10) unsigned NOT NULL default '0',
  `last_discussion` int(10) unsigned default NULL,
  `form_in_bottom` enum('Y','N') NOT NULL default 'N',
  `dont_trace_books` varchar(250) default NULL,
  `default_page` tinyint(3) unsigned NOT NULL default '0',
  `highlight_nick` tinyint(3) unsigned NOT NULL default '1',
  `as_book` enum('Y','N') NOT NULL default 'N',
  `sort_type` enum('Y','N') NOT NULL default 'N',
  PRIMARY KEY  (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_priority` (
  `user_id` int(10) unsigned NOT NULL default '0',
  `priority_id` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`user_id`,`priority_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_rule` (
  `rule_id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `description` text,
  `priority` tinyint(3) unsigned NOT NULL default '50',
  `need_warn` enum('Y','N') NOT NULL default 'N',
  `need_block` enum('Y','N') NOT NULL default 'N',
  `hidden` enum('Y','N') NOT NULL default 'N',
  PRIMARY KEY  (`rule_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_user_read` (
  `userid` int(10) unsigned NOT NULL default '0',
  `discussion_id` int(10) unsigned NOT NULL default '0',
  `last_read` int(10) unsigned default NULL,
  `prev_read` int(10) unsigned default NULL,
  `dont_trace` enum('Y','N') NOT NULL default 'N',
  PRIMARY KEY  (`userid`,`discussion_id`),
  KEY `discussion_id` (`discussion_id`,`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_warning` (
  `warning_id` int(10) unsigned NOT NULL auto_increment,
  `user_id` int(10) unsigned NOT NULL default '0',
  `message_id` int(10) unsigned NOT NULL default '0',
  `rule_id` int(10) unsigned NOT NULL default '0',
  `created` datetime NOT NULL default '0000-00-00 00:00:00',
  `created_by` int(10) unsigned default NULL,
  `comment` text,
  `block_id` int(10) unsigned default NULL,
  PRIMARY KEY  (`warning_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_vote_options` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `discussion_id` int(10) unsigned NOT NULL,
  `title` varchar(100) NOT NULL default '',
  `created_by` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`id`),
  KEY `discussion_id` (`discussion_id`,`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfb_vote` (
  `discussion_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `option_id` int(10) unsigned NOT NULL,
  PRIMARY KEY  (`discussion_id`, `user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfcms_page` (
  `page_id` int(10) unsigned NOT NULL auto_increment,
  `page_name` varchar(250) NOT NULL,
  `page_url` varchar(250) default NULL,
  `page_parent` int(10) unsigned default NULL,
  `page_content` mediumtext,
  `is_active` enum('Y','N') NOT NULL default 'Y',
  `new_window` enum('Y','N') NOT NULL default 'N',
  `priority` tinyint(3) unsigned NOT NULL default '50',
  `realm_id` varchar(15) default NULL,
  `image` varchar(250) default NULL,
  `bottom_menu` enum('Y','N') NOT NULL default 'N',
  `display_type_id` varchar(250) default NULL,
  `display_obj_id` int(10) unsigned default NULL,
  PRIMARY KEY  (`page_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `gfmsg_message` (
  `msg_id` int(10) unsigned NOT NULL auto_increment,
  `from_id` int(10) unsigned NOT NULL default '0',
  `from_nick` varchar(255) NOT NULL default '',
  `to_id` int(10) unsigned NOT NULL default '0',
  `to_nick` varchar(255) NOT NULL default '',
  `date` datetime NOT NULL default '0000-00-00 00:00:00',
  `subject` varchar(255) default NULL,
  `text` text NOT NULL,
  `coming_id` int(10) unsigned default NULL,
  PRIMARY KEY  (`msg_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8284 DEFAULT CHARSET=utf8;

CREATE TABLE `gfmsg_read` (
  `msg_id` int(10) unsigned NOT NULL default '0',
  `user_id` int(10) unsigned NOT NULL default '0',
  `is_from` enum('Y','N') NOT NULL default 'Y',
  `read_date` datetime default NULL,
  PRIMARY KEY  (`msg_id`,`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

insert into gf_realm (realm_id,        description) values
                     ('S_ADMIN',      'Super Administrator'),
                     ('CMS_ADMIN',    'Администратор Контента'),
                     ('BOOK_ADMIN',   'Администратор гостевых книг'),
                     ('BOOK_RULES',   'Редактор правил книги'),
                     ('BOOK_MAIL',    'Почта модераторов'),
                     ('GFMSG_CREATE', 'Написание новых личных сообщений'),
                     ('BOOK_RATING',  'Просмотр рейтингов книг');

insert into gf_realm (realm_id,       description,                           is_restrict) values
                     ('GFMSG_BLOCK', 'Блокировка отправки личных сообщений', 'Y'),
                     ('BOOK_BLOCK',  'Блокировка на гостевой книге',         'Y');

insert into gf_access (realm_id,       user_id) values
                      ('S_ADMIN',      1),
                      ('CMS_ADMIN',    1),
                      ('BOOK_ADMIN',   1),
                      ('BOOK_RULES',   1),
                      ('BOOK_MAIL',    1),
                      ('GFMSG_CREATE', 1),
                      ('BOOK_RATING',  1);
