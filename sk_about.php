<?php
/**********************************************************************************************
 Spam Karma (c) 2015 - http://github.com/strider72/spam-karma

 This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; version 2 of the License.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

************************************************************************************************/
?><div class="sk_first wrap sk_about">
<blockquote><p lang="it"><i>Lasciate ogni speranza, voi ch'entrate</i></p>
<p class="source"><?php _e("Some dead Italian dude", 'spam-karma'); ?></p></blockquote>
<blockquote><p><i><?php _e("Hey... Wanna play some poker?", 'spam-karma'); ?></i></p>
<p class="source"><?php _e("Some Internet jackass", 'spam-karma'); ?></p></blockquote>
<br/>
<h2 class="sk_first"><?php _e("The Machine", 'spam-karma'); ?></h2>
<p><strong><?php _e('<a href="http://github.com/strider72/spam-karma" target="_blank">Spam Karma 2</a></strong> is the proud successor to Spam Karma, with whom it shares most of the development ideas, but absolutely none of the code. It is meant to stop all forms of automated Blog spam effortlessly, while remaining as unobtrusive as possible to regular commenters.', 'spam-karma');?></p>

<h2><?php _e("The People", 'spam-karma'); ?></h2>
<p><strong><?php _e('<a href="http://unknowngenius.com/blog/me/" target="_blank">Dr Dave</a></strong> is a card-carrying Evil Genius still busy plotting world domination from his secret hideout in the Parisian catacombs (having recently relocated from his volcanic lair in Tokyo, Japan).
When he is not wasting his time coding ridiculously elaborate anti-spam plugins, he also writes somewhat offensive entries on miscellaneous pointless matters in <a href="http://unknowngenius.com/blog/" target="_blank">his blog</a>.', 'spam-karma'); ?></p>
<p><?php _e('<a href="http://striderweb.com/nerdaphernalia/">Stephen Rider</a> has been keeping the lights on and the floors swept since Dr Dave released Spam Karma into the wilds of the GPL.  It is his hope that the requisite community will form strongly enough to keep this gem of a plugin going.  Thanks are also due to the "owners" of the project on Google Code who have contributed code to the long awaited Next Version.','spam karma'); ?></p>

<p><?php _e("Many, many people have, one way or another, contributed to Spam Karma... including, in alphabetical order:", 'spam-karma'); ?></p>
<fieldset class="sk_cast"><legend><b><?php _e("Mad Coding Skillz", 'spam-karma'); ?></b></legend>
<p><?php
	$coders = array("James Seward" => "http://www.grooblehonk.co.uk/",
"Matt Read" => "http://mattread.com/",
"Mark Jaquith" => "http://txfx.net/category/internet/wordpress/",
"Peter Westwood" => "http://blog.ftwr.co.uk/",
"Owen Winkler" => "http://www.asymptomatic.net/",
"Jason" => "http://ushimitsudoki.com/",
"Drac" => "http://fierydragon.org/",
'Alexander Concha' => 'http://www.buayacorp.com/',
'priv' => 'http://privism.org/blog/',
'Priit Laes' => 'http://plaes.org/'
);

	ksort($coders);
	$i = count($coders);
	foreach ($coders as $name => $url)
	{
		echo "<a href=\"$url\">$name</a>";
		$i--;
		if ($i == 1)
			echo " & ";
		elseif ($i)
			echo ", ";
	
	}
?>
</p></fieldset>


<fieldset class="sk_cast"><legend><b>International Team</b></legend>
<p><?php
	$coders = array(
	"Tsuyoshi Fukuda" => array("http://tsulog.com", "jp_JP"),
	"Xavier Borderie" => array("http://xavier.borderie.net/blog/", "fr_FR"),
	"BlueValentine" => array("http://www.AlchemicoBlu.it", "it_IT"),
	"Pal" => array("", "zh_CN"),
	"Marcel Bischoff" => array("", "de_DE"),
	"Anne" => array("", "nl_NL"),
	"Joshua Sigar" => array("", "di_DI"),
	"César Gómez Martín" => array("http://www.cesar.tk/", "es_ES"),
	"Jan Bauer" => array("", "de_DE"),
	"Michael Boman" => array("", "sv_SE"),
	"Johan Folin" => array("", "sv_SE"),
	"Dafydd Tomos" => array("http://da.fydd.org/blog/", "cy_GB"),
	);

	ksort($coders);
	$i = count($coders);
	foreach ($coders as $name => $info)
	{
		if ($info[1] == WPLANG)
			echo "<strong>";
		if ($info[0])
			echo "<a href=\"$info[0]\">$name</a>";
		else
			echo "$name";
		echo " (". (substr($info[1], 0, 2)) . ")";
		if ($info[1] == WPLANG)
			echo "</strong>";
		
		$i--;
		if ($i == 1)
			echo " & ";
		elseif ($i)
			echo ", ";
	
	}
?>
</p></fieldset>
<p>Please <a href="http://github.com/strider72/spam-karma/issues">drop us a line</a> if you too would like to contribute to Spam Karma.</p>

</div>