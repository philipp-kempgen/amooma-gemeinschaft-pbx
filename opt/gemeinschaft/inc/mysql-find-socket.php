<?php
/*******************************************************************\
*            Gemeinschaft - asterisk cluster gemeinschaft
* 
* $Revision$
* 
* Copyright 2007, amooma GmbH, Bachstr. 126, 56566 Neuwied, Germany,
* http://www.amooma.de/
* Stefan Wintermeyer <stefan.wintermeyer@amooma.de>
* Philipp Kempgen <philipp.kempgen@amooma.de>
* Peter Kozak <peter.kozak@amooma.de>
* 
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
* MA 02110-1301, USA.
\*******************************************************************/

defined('GS_VALID') or die('No direct access.');

require_once( GS_DIR .'inc/quote_shell_arg.php' );


function _grep_mysql_sockets( $str )
{
	$sockets = array();
	if (preg_match_all( '/(?<f>\/(?:var|tmp)\/[a-zA-Z0-9.\-_\/]+)/', $str, $matches, PREG_SET_ORDER ) > 0) {
		foreach ($matches as $m) {
			$filename = $m['f'];
			if (@file_exists($filename)) {
				if (@fileType($filename) === 'socket') {
					$sockets[] = $filename;
				}
			}
		}
		$sockets = array_unique( $sockets );
	}
	return $sockets;
}

function gs_mysql_find_socket( $db_host )
{
	$socket = null;

	# never use socket for remote databases
	if (! in_array((string)$db_host, array('127.0.0.1', 'localhost', ''), true)) {
		return null;
	}
	
	/*
	wo der Socket liegt findet man so heraus:
	mysqladmin variables | grep sock
	oder es steht auch in der MySQL-Konfiguration:
	cat /etc/my.cnf | grep sock
	bzw.
	cat /etc/mysql/my.cnf | grep sock
	
	Achtung, kann auch hier sein (Debian):
	/etc/mysql/my.cnf
	/etc/mysql/debian.cnf
	/etc/mysql/mariadb.conf.d/50-mysqld_safe.cnf
	/etc/mysql/mariadb.conf.d/50-server.cnf
	
	CentOS:
	/etc/my.cnf
	*/
	
	$sockets = array();
	$err=0; $out=array();
	@exec( '( find /etc/mysql/ -name *.cnf -type f -print0 2>>/dev/null \
		; find /etc/my.cnf -type f -print0 2>>/dev/null ) \
		| xargs -0 -L 1 -n 1 --no-run-if-empty sed -e '. qsa('/^\[\(mysqld\|mysqld_safe\|safe_mysqld\|client\|mysql_upgrade\)\]/,/^\[/!d') .' \
		| grep "^socket\\b" 2>>/dev/null', $out, $err );
	if ($err === 0) {
		$sockets = _grep_mysql_sockets( implode( "\n", $out ));
	}
	
	if (count( $sockets ) !== 0) {
		gs_log(GS_LOG_DEBUG, 'Found MySQL sockets in MySQL config.: '. implode(', ', $sockets));
	} else {
		gs_log(GS_LOG_NOTICE, 'Did not find MySQL sockets in MySQL config.');
			$err=0; $out=array();
			# We have to use sudo here to connect to MySQL
			# as root (alternatively make sure the Apache
			# user has access to MySQL).
			@exec( 'sudo -n -- mysqladmin -s variables | grep -E -e \'\\bsocket\\b\' 2>>/dev/null', $out, $err );
			// should work everywhere if mysqladmin is available
			if ($err === 0) {
				$sockets = _grep_mysql_sockets( implode( "\n", $out ));
			}
			
			if (count( $sockets ) !== 0) {
				gs_log(GS_LOG_DEBUG, 'Found MySQL sockets via mysqladmin: '. implode(', ', $sockets));
			} else {
				gs_log(GS_LOG_NOTICE, 'Did not find MySQL sockets via mysqladmin.');
				gs_log(GS_LOG_WARNING, 'Could not find MySQL socket');
			}
	}
	
	return ((count( $sockets ) !== 0) ? $socket[0] : null );
}


?>