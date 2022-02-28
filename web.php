<?php
/*
    CDB Web UI
    Copyright (C) 2016 Dmitry V. Zimin

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if(!defined('ROOTDIR'))
{
	define('ROOTDIR', dirname(__FILE__).DIRECTORY_SEPARATOR);
}

if(!file_exists(ROOTDIR.'inc.config.php'))
{
	exit;
}

require_once(ROOTDIR.'inc.config.php');


	session_name('ZID');
	session_start();
	error_reporting(E_ALL);
	define('Z_PROTECTED', 'YES');

	$self = $_SERVER['PHP_SELF'];

	if(!empty($_SERVER['HTTP_CLIENT_IP'])) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif(!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$ip = @$_SERVER['REMOTE_ADDR'];
	}

	require_once(ROOTDIR.'language'.DIRECTORY_SEPARATOR.'ru.php');
	require_once(ROOTDIR.'inc.db.php');
	require_once(ROOTDIR.'inc.ldap.php');
	require_once(ROOTDIR.'inc.user.php');
	require_once(ROOTDIR.'inc.utils.php');
	require_once(ROOTDIR.'inc.flags.php');

	$action = '';
	if(isset($_GET['action']))
	{
		$action = $_GET['action'];
	}

	$id = 0;
	if(isset($_GET['id']))
	{
		$id = $_GET['id'];
	}

	if($action == 'message')
	{
		switch($id)
		{
			default:
				$error_msg = 'Unknown error';
				break;
		}

		include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.message.php');
		exit;
	}

	$db = new MySQLDB(DB_RW_HOST, NULL, DB_USER, DB_PASSWD, DB_NAME, DB_CPAGE, TRUE);

	$ldap = new LDAP(LDAP_URI, LDAP_USER, LDAP_PASSWD, FALSE);
	$user = new UserAuth($db, $ldap);

	if(!$user->get_id())
	{
		header('Content-Type: text/html; charset=utf-8');
		switch($action)
		{
			case 'logon':
			{
				if(!$user->logon(@$_POST['login'], @$_POST['passwd']))
				{
					$error_msg = 'Неверное имя пользователя или пароль!';
					include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.login.php');
					exit;
				}

				if(!$user->is_member(LDAP_ADMIN_GROUP_DN))
				{
					$user->logoff();
					$error_msg = 'Access denied!';
					include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.login.php');
					exit;
				}

				header('Location: '.$self);
			}
			exit;

			case 'login':  // show login form
			{
				include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.login.php');
			}
			exit;
		}
	}

	if(!$user->get_id())
	{
		include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.login.php');
		exit;
	}

	switch($action)
	{
		case 'logoff':
		{
			$user->logoff();
			include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.login.php');
		}
		exit;

		case 'computerinfo':
		{
			if(!$id)
			{
				break;
			}

			if($db->select_assoc_ex($result, rpv("
				SELECT
					c.`name`
				FROM @computers AS c
				WHERE c.`id` = {d0}
			",
				$id
			)))
			{
				$computer = $result[0]['name'];
			}

			$db->select_assoc_ex($result, rpv("
				SELECT
					f.`id`,
					f.`path`,
					f.`filename`,
					f.`flags`
				FROM @files_inventory AS fi
				LEFT JOIN @files AS f
					ON fi.`fid` = f.`id`
				WHERE
					-- (fi.`flags` & {%FIF_DELETED}) = 0                         -- File not Deleted
					-- AND 
					-- (f.`flags` & {%FF_ALLOWED}) = 0                      -- Not exist in IT Invent
					-- AND 
					fi.`pid` = {d0}
				ORDER
					BY f.`path`, f.`filename`
			",
				$id
			));

			include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.computer-info.php');
		}
		exit;
		
		case 'pathinfo':
		{
			if(!$id)
			{
				break;
			}
			
			$offset = 0;
			$total = 0;

			if(isset($_GET['offset']))
			{
				$offset = $_GET['offset'];
			}			

			if($db->select_assoc_ex($result, rpv("
				SELECT
					f.`path`,
					f.`filename`
				FROM @files AS f
				WHERE f.`id` = {d0}
			",
				$id
			)))
			{
				$path = $result[0]['path'];
				$filename = $result[0]['filename'];
			}

			if($db->select_ex($result, rpv("
				SELECT
					COUNT(*)
				FROM @files_inventory AS fi
				WHERE fi.`fid` = {d0}
			",
				$id
			)))
			{
				$total = $result[0][0];
			}

			$db->select_assoc_ex($result, rpv("
				SELECT
					c.`id`,
					c.`name`,
					c.`flags`
				FROM @files_inventory AS fi
				LEFT JOIN @computers AS c ON c.`id` = fi.`pid`
				WHERE fi.`fid` = {d0}
				GROUP BY fi.`pid`
				ORDER BY c.`name`
				LIMIT {d1},100
			",
				$id,
				$offset
			));

			include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.list-computers.php');
		}
		exit;
		
		case 'find':
		{
			if(!isset($_GET['search']))
			{
				break;
			}

			$offset = 0;
			$total = 0;
			
			if(isset($_GET['offset']))
			{
				$offset = $_GET['offset'];
			}			

			$search = sql_escape(trim($_GET['search']));
			
			if($db->select_ex($result, rpv("
				SELECT
					COUNT(*)
				FROM @files AS f
				WHERE
					f.`path` LIKE '%{r0}%'
					OR f.`filename` LIKE '%{r0}%'
				ORDER BY f.`path`
			",
				$search
			)))
			{
				$total = $result[0][0];
			}

			$db->select_assoc_ex($result, rpv("
				SELECT
					f.`id`,
					f.`path`,
					f.`filename`,
					f.`flags`
				FROM @files AS f
				WHERE
					f.`path` LIKE '%{r0}%'
					OR f.`filename` LIKE '%{r0}%'
				ORDER BY f.`path`
				LIMIT {d1},100
			",
				$search,
				$offset
			));

			include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.main.php');
		}
		exit;

		case 'findcomputer':
		{
			if(!isset($_GET['searchpc']))
			{
				break;
			}

			$offset = 0;
			$total = 0;
			
			if(isset($_GET['offset']))
			{
				$offset = $_GET['offset'];
			}

			$path = '';
			$filename = '';
			$search = sql_escape(trim($_GET['searchpc']));
			
			if($db->select_ex($result, rpv("
				SELECT
					COUNT(*)
				FROM @computers AS c
				WHERE
					c.`name` LIKE '%{r0}%'
				ORDER BY c.`name`
			",
				$search
			)))
			{
				$total = $result[0][0];
			}

			$db->select_assoc_ex($result, rpv("
				SELECT
					c.`id`,
					c.`name`,
					c.`flags`
				FROM @computers AS c
				WHERE
					c.`name` LIKE '%{r0}%'
				ORDER BY c.`name`
				LIMIT {d1},100
			",
				$search,
				$offset
			));

			include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.list-computers.php');
		}
		exit;
	}

	header('Content-Type: text/html; charset=utf-8');

	include(ROOTDIR.'templ'.DIRECTORY_SEPARATOR.'tpl.main.php');
