<?php

use Friendica\App;
use Friendica\Core\System;

require_once('include/crypto.php');

function xrd_init(App $a) {
	if ($a->argv[0] == 'xrd') {
		$uri = urldecode(notags(trim($_GET['uri'])));
		if ($_SERVER['HTTP_ACCEPT'] == 'application/jrd+json') {
			$mode = 'json';
		} else {
			$mode = 'xml';
		}
	} else {
		$uri = urldecode(notags(trim($_GET['resource'])));
		if ($_SERVER['HTTP_ACCEPT'] == 'application/xrd+xml') {
			$mode = 'xml';
		} else {
			$mode = 'json';
		}
	}

	if(substr($uri,0,4) === 'http') {
		$acct = false;
		$name = basename($uri);
	} else {
		$acct = true;
		$local = str_replace('acct:', '', $uri);
		if(substr($local,0,2) == '//')
			$local = substr($local, 2);

		$name = substr($local, 0, strpos($local,'@'));
	}

	$r = dba::select('user', array(), array('nickname' => $name), array('limit' => 1));
	if (!dbm::is_result($r)) {
		killme();
	}

	$profile_url = System::baseUrl().'/profile/'.$r['nickname'];

	if ($acct) {
		$alias = $profile_url;
	} else {
		$alias = 'acct:'.$r['nickname'].'@'.$a->get_hostname();

		if ($a->get_path()) {
			$alias .= '/'.$a->get_path();
		}
	}

	if ($mode == 'xml') {
		xrd_xml($a, $uri, $alias, $profile_url, $r);
	} else {
		xrd_json($a, $uri, $alias, $profile_url, $r);
	}
}

function xrd_json($a, $uri, $alias, $profile_url, $r) {
	$salmon_key = salmon_key($r['spubkey']);

	header('Access-Control-Allow-Origin: *');
	header("Content-type: application/json; charset=utf-8");

	$json = array('subject' => $uri,
			'aliases' => array($alias),
			'links' => array(array('rel' => NAMESPACE_DFRN, 'href' => $profile_url),
					array('rel' => NAMESPACE_FEED, 'type' => 'application/atom+xml', 'href' => System::baseUrl().'/dfrn_poll/'.$r['nickname']),
					array('rel' => 'http://webfinger.net/rel/profile-page', 'type' => 'text/html', 'href' => $profile_url),
					array('rel' => 'http://microformats.org/profile/hcard', 'type' => 'text/html', 'href' => System::baseUrl().'/hcard/'.$r['nickname']),
					array('rel' => NAMESPACE_POCO, 'href' => System::baseUrl().'/poco/'.$r['nickname']),
					array('rel' => 'http://webfinger.net/rel/avatar', 'type' => 'image/jpeg', 'href' => System::baseUrl().'/photo/profile/'.$r['uid'].'.jpg'),
					array('rel' => 'http://joindiaspora.com/seed_location', 'type' => 'text/html', 'href' => System::baseUrl()),
					array('rel' => 'salmon', 'href' => System::baseUrl().'/salmon/'.$r['nickname']),
					array('rel' => 'http://salmon-protocol.org/ns/salmon-replies', 'href' => System::baseUrl().'/salmon/'.$r['nickname']),
					array('rel' => 'http://salmon-protocol.org/ns/salmon-mention', 'href' => System::baseUrl().'/salmon/'.$r['nickname'].'/mention'),
					array('rel' => 'http://ostatus.org/schema/1.0/subscribe', 'template' => System::baseUrl().'/follow?url={uri}'),
					array('rel' => 'magic-public-key', 'href' => 'data:application/magic-public-key,'.$salmon_key)
));
	echo json_encode($json);
	killme();
}

function xrd_xml($a, $uri, $alias, $profile_url, $r) {
	$salmon_key = salmon_key($r['spubkey']);

	header('Access-Control-Allow-Origin: *');
	header("Content-type: text/xml");

	$tpl = get_markup_template('xrd_person.tpl');

	$o = replace_macros($tpl, array(
		'$nick'        => $r['nickname'],
		'$accturi'     => $uri,
		'$alias'       => $alias,
		'$profile_url' => $profile_url,
		'$hcard_url'   => System::baseUrl() . '/hcard/'         . $r['nickname'],
		'$atom'        => System::baseUrl() . '/dfrn_poll/'     . $r['nickname'],
		'$zot_post'    => System::baseUrl() . '/post/'          . $r['nickname'],
		'$poco_url'    => System::baseUrl() . '/poco/'          . $r['nickname'],
		'$photo'       => System::baseUrl() . '/photo/profile/' . $r['uid']      . '.jpg',
		'$baseurl' => System::baseUrl(),
		'$salmon'      => System::baseUrl() . '/salmon/'        . $r['nickname'],
		'$salmen'      => System::baseUrl() . '/salmon/'        . $r['nickname'] . '/mention',
		'$subscribe'   => System::baseUrl() . '/follow?url={uri}',
		'$modexp'      => 'data:application/magic-public-key,'  . $salmon_key,
		'$bigkey'      => salmon_key($r['pubkey']),
	));

	$arr = array('user' => $r, 'xml' => $o);
	call_hooks('personal_xrd', $arr);

	echo $arr['xml'];
	killme();
}
