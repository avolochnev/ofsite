# Ofsite

Web-сайт с поддержкой гостевых книг и простейшей CMS

## Требования

- PHP 5.2.4+
-- модуль mysql
-- молудь mb_string
-- Библиотека Twig 1.12+
- MySQL 5
- Apache 2 веб-сервер
-- rewrite_module
-- php5_module

## Установка

- установить и настроить Apache, MySQL, Twig;
- создать базу данных ( mysql> CREATE DATABASE ofsite CHARACTER SET utf8; )
-- при необходимости создать пользователя в mysql и настроить доступ к базе данных
- cd %DocumentRoot%
-- git clone https://github.com/avolochnev/ofsite.git
-- создать структуру таблиц ( mysql ofsite < ofsite/system/db/001_init.sql )
-- в ofsite/app/config/config.php
--- настроить содединение с базой данных
--- настроить путь к Twig
- настроить переадресацию запросов к приложенив в httpd.conf или .htaccess (см. ниже)
- первый зарегистрировавшийся пользователь автоматически становится администратором
- создать основную книгу (главная страница, меню "Добавить книгу")

### Пример настройки httpd.conf

    LoadModule rewrite_module modules/mod_rewrite.so
    LoadModule php5_module "/path/to/php/module"

    <Directory "%DocumentRoot%">
        AllowOverride All
    </Directory>

    <IfModule dir_module>
        DirectoryIndex index.html index.php
    </IfModule>

    AddHandler application/x-httpd-php .php

### Пример настройки в .htaccess

Пример настройки прилжения (предполагается, что приложение развернуто в %DocumentRoot%, и будет доступно через //yourhost/board/)

    RewriteEngine on
    RewriteRule ^$ /board/ [R]
    RewriteRule ^board$ /board/ [R]
    RewriteRule ^board/([a-z_]*)\.phtml(.*)$ ofsite/app/index.php?controller=$1 [QSA,L]
    RewriteRule ^board/$ ofsite/app/index.php [L]
    RewriteRule ^board/assets/(.*)$ ofsite/system/assets/$1 [L]
    RewriteRule ^board/(.*)$ ofsite/app/$1 [L]
