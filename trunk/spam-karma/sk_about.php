<?php
/**********************************************************************************************
 Spam Karma 2 (c) 2008 - Dave A. duVerle - http://unknowngenius.com

 This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; version 2 of the License.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

************************************************************************************************/
?><div class="sk_first wrap sk_about">
<blockquote><p><i>Lasciate ogni speranza, voi ch'entrate</i></p>
<p class="source"><?php _e("Some dead Italian dude", 'sk2'); ?></p></blockquote>
<blockquote><p><i><?php _e("Hey... Wanna play some poker?", 'sk2'); ?></i></p>
<p class="source"><?php _e("Some Internet jackass", 'sk2'); ?></p></blockquote>
<br/>
<h2 class="sk_first"><?php _e("The Machine", 'sk2'); ?></h2>
<p><strong><?php _e('<a href="http://unknowngenius.com/blog/wordpress/spam-karma" target="_blank">Spam Karma 2</a></strong> is the proud successor to Spam Karma, with whom it shares most of the development ideas, but absolutely none of the code. It is meant to stop all forms of automated Blog spam effortlessly, while remaining as unobtrusive as possible to regular commenters.', 'sk2');?></p>

<h2><?php _e("The Man", 'sk2'); ?></h2>
<p><strong><?php _e('<a href="http://unknowngenius.com/blog/me/" target="_blank">Dr Dave</a></strong> is a card-carrying Evil Genius still busy plotting world domination from his secret hideout in the Parisian catacombs (having recently relocated from his volcanic lair in Tokyo, Japan).
When he is not wasting his time coding ridiculously elaborate anti-spam plugins, he also writes somewhat offensive entries on miscellaneous pointless matters in <a href="http://unknowngenius.com/blog/" target="_blank">his blog</a>.', 'sk2'); ?></p>

<h2><?php _e("The Bribes", 'sk2'); ?></h2>
<p><?php _e('Donations are in <em>no way</em> mandatory.', 'sk2'); ?></p>
<p><?php _e('Yet, I have been spending considerable amounts of time writing, supporting and debugging both SK1 and SK2. Therefore, if you like what you see, and want to see more of it in the future, feel free to help me finance my many costly addictions by droping a buck or two in the tip jar (<a href="http://unknowngenius.com/blog/archives/2006/01/30/the-state-of-spam-karma/#donations">more reasons why you should</a>)...', 'sk2'); ?></p>
<p><form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="text-align:center;">
<input type="radio" name="amount" value="2.00" checked="checked"/>$2.00<br />
<input type="radio" name="amount" value="5.00"/>$5.00<br />
<input type="radio" name="amount" value="10.00"/>$10.00<br />
<input type="radio" name="amount" value="20.00"/>$20.00<br />
<input type="radio" name="amount" value="30.00"/>$30.00<br />
<input type="radio" name="amount" value="50.00"/>$50.00<br />
<input type="radio" name="amount" value="666.00"/>$666.00<br />
<input type="hidden" name="cmd" value="_xclick"/><input type="hidden" name="business" value="paypal@unknowngenius.com"><input type="hidden" name="item_name" value="Buy Dr Dave a Lifetime supply of Bombay Sapphire Fund"/><input type="hidden" name="no_shipping" value="1"/><input type="hidden" name="return" value="http://unknowngenius.com/blog/" /><input type="hidden" name="cancel_return" value="http://unknowngenius.com/blog/"/><input type="hidden" name="currency_code" value="USD"/><input type="hidden" name="tax" value="0"/><input type="hidden" name="bn" value="PP-DonationsBF"/><input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but21.gif" border="0" name="submit" alt="Make payments with PayPal - it's fast, free and secure!" style="border: none;"/></form>
</p>
<p><?php _e('On behalf of Dr Dave Inc., I present you with our guarantee that no less than 25% of all proceedings will go directly toward the purchase of the finest <a href="http://www.physics.uq.edu.au/people/nieminen/bombay_sapphire.html" target="_blank">Bombay Sapphire</a> this city has to offer.', 'sk2'); ?></p>
<p><?php _e('Kind words of support or constructive comments are always welcome as well. Please use this <a href="http://unknowngenius.com/blog/wordpress/spam-karma/sk2-contact-form/">online form</a> to contact me.', 'sk2'); ?></p>
<h2><?php _e("The Cast", 'sk2'); ?></h2>
<p><?php _e("Many, many people have, one way or another, contributed to Spam Karma... Including, in alphabetical order:", 'sk2'); ?></p>
<fieldset class="sk2_cast"><legend><b><?php _e("Mad Coding Skillz", 'sk2'); ?></b></legend>
<p><?php
	$coders = array("James Seward" => "http://www.grooblehonk.co.uk/",
"Matt Read" => "http://mattread.com/",
"Mark Jaquith" => "http://txfx.net/category/internet/wordpress/",
"Peter Westwood" => "http://blog.ftwr.co.uk/",
"Owen Winkler" => "http://www.asymptomatic.net/",
"Jason" => "http://ushimitsudoki.com/",
"Drac" => "http://fierydragon.org",
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


<fieldset class="sk2_cast"><legend><b>International Team</b></legend>
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
	echo "</p><p><em>Please email me your name+URL+language if you have contributed to SK2's l10n effort</em>";
?>
</p></fieldset>

<fieldset class="sk2_cast"><legend><b><?php _e("Fearless Guinea Pigs, Generous Souls and All Around Cool People", 'sk2'); ?></b></legend>
<p><?php
	$testers = array('Adam Harvey'=>'http://www.organicmechanic.org/',
'Admiral Justin'=>'http://s89631316.onlinehome.us/',
'Adrian Cooke'=>'http://zero2180.net/',
'Aine'=>'http://evilqueen.demesnes.net/',
'Ainslie'=>'http://webgazette.co.uk/',
'Ajay D\'Souza'=>'http://www.ajaydsouza.com',
'Akin Budi'=>'http://akinbudi.info/',
'Alan H Levine'=>'http://dommy.com/alan/',
'Alex Smith'=>'http://blog.alexsmith.org/',
'Alexander Middleton'=>'http://www.time-meddler.co.uk',
'Alexander Trust'=>'http://www.sajonara.de/',
'Alhena'=>'http://www.alhena.net/blog/',
'Aly de Villers'=>'http://www.conejoaureo.com/',
'Andreas Schuh'=>'http://www.greenlemon.org',
'Andreas Streim'=>'http://www.streim.de/',
'Andrew Lenzer'=>'http://uncle-andrew.net',
'André Fiebig'=>'http://www.finanso.de',
'Ari Kontiainen'=>'',
'Asians in Media'=>'http://www.asiansinmedia.org',
'Astronomy Online'=>'http://astronomyonline.org',
'Athanasios Alexandrides'=>'http://artpixels.com/',
'B John Masters'=>'',
'Basil Hashem'=>'http://labs.mozilla.com',
'Bear Shirt Press'=>'http://www.bearshirtpress.com/',
'Ben Margolin'=>'http://blog.benjolo.com/',
'Bill Bumgarner'=>'http://www.friday.com/bbum/',
'Bonnie Russell'=>'',
'Bonnie Wren'=>'http://www.bonniewren.com/',
'Brendon Connelly'=>'http://slackermanager.com',
'Brian Bonner'=>'http://uncooperativeblogger.com/',
'Brian Epps'=>'http://randomnumbers.us/',
'Carsten Stracke'=>'http://www.subaquasternalrubs.com/',
'Cary Miller'=>'http://www.cancer-news-watch.com',
'Catherine Harris'=>'',
'Charles Arthur'=>'http://www.charlesarthur.com/blog/',
'Charlie Parekh'=>'',
'Charolette Maire'=>'http://dianne.free.fr/',
'Chris Aves'=>'http://ninthspace.org/blog/',
'Chris Church'=>'',
'Chris Coggburn'=>'http://chris.coggburn.us/',
'Chris Davis'=>'http://chrisjdavis.org/',
'Chris Kelly'=>'http://www.ckelly.net/journal/',
'Chris McLaren'=>'http://www.chrismclaren.com/blog/',
'Chris Rose'=>'http://www.offlineblog.com',
'Chris Teodorski'=>'http://chris.teodorski.com',
'Christa'=>'http://awfulsouls.artfinity.co.uk/',
'Christa St. Jean'=>'http://www.awfulsouls.com/blog/',
'Christian Mohn'=>'http://h0bbel.p0ggel.org',
'Christoph Schmalz'=>'http://schmalz.net/',
'Christopher'=>'http://www.whatintarnation.net/blog/',
'Christopher Gentle'=>'http://brainsnorkel.com',
'Christopher Van Epps'=>'http://www.cvanepps.com',
'Christopher W'=>'http://www.whatintarnation.net/blog',
'Craig Kaplan'=>'',
'Cricket'=>'http://randomchirps.ws/',
'Dale LeDoux'=>'',
'Dan Greene'=>'http://www.librarymonk.com/',
'Dan Milward'=>'http://instinct.co.nz/',
'Dan Tobin'=>'http://www.dantobindantobin.com/blog/',
'Dangereuse Trilingue'=>'http://dangereusetrilingue.net/',
'Daniel Vollmer'=>'http://www.maven.de/',
'Daniel Voyles'=>'http://blog.danvoyles.us/',
'Darren John Rowse'=>'http://problogger.net/',
'Darryl Holman'=>'http://hominidviews.com',
'Dave Broome'=>'http://db.rambleschmack.net/',
'Dave P.'=>'http://ncschoolmatters.com',
'Daveb'=>'http://davebgimp.com/',
'David Belado'=>'',
'David Boulanger'=>'',
'David Gibbs'=>'http://david.fallingrock.net/',
'David Herren'=>'http://www.fhuhs.org',
'David Stacy'=>'http://nixguy.com',
'Deamos'=>'http://thetriadofdestruction.com/',
'Dimitris Glezos'=>'http://dimitris.glezos.com/',
'Drop Off Depot'=>'http://www.dropoffdepot.com/',
'Ed Bott'=>'http://www.edbott.com/weblog/',
'Eddie H.'=>'http://www.thatgingerguy.com',
'Edward Dickey'=>'',
'Edward Mitchell'=>'http://mitchellconsulting.net/commonsense/',
'Elizabeth Campbell'=>'http://www.xeney.com/',
'Enno ter Keurst'=>'http://terkeurst.org',
'Erik Peterson'=>'',
'Erwin Harte'=>'http://is-here.com/',
'Etanisla Lopez-Ortiz'=>'http://www.carelessthought.com/',
'Fabienne Dubosson'=>'http://www.maplanete.ch/carnet/',
'Firas'=>'http://firasd.org',
'Fraser Lewry'=>'http://www.blogjam.com/',
'GW'=>'http://www.winorama.com.au',
'Gary Sweitzer'=>'http://www.diginkstudios.com/',
'Geof Morris'=>'http://gfmorris.net',
'George Maxwell'=>'',
'Gerardo Arnaez'=>'',
'Ginger'=>'http://the-ultimate-journey.com',
'Glutnix'=>'http://www.webfroot.co.nz',
'Gonéri Le Bouder'=>'http://alice.rulezlan.org/blog/',
'Grant Cummings'=>'http://www.pepperguy.com/newblog/',
'Greenzones'=>'http://gliving.tv/',
'Gregory Yorke'=>'http://IndependentSources.com',
'Gustavo Barrón'=>'http://blog.idealabs.tk/',
'Hakan Svensson'=>'http://www.AdventureDad.com',
'Halane Hughes'=>'',
'Harry Teasley'=>'http://www.factoryofinfinitebliss.com',
'Henrik'=>'http://myworld.se/',
'Hilary'=>'http://www.superjux.com/',
'Indiana Jones\' School of Management'=>'http://ijsm.org/',
'Indranil'=>'http://design.troidus.com/',
'J.D. Hodges'=>'http://www.jdhodges.com',
'Jabley'=>'http://www.jabley.com/',
'Jacob and Claire'=>'http://www.estelledesign.com/what/',
'James T.'=>'http://mapofthedead.com/',
'James Tippett'=>'',
'Jana'=>'http://www.cloudsinmycoffee.com/',
'Jana S Bosarge'=>'',
'Japundit'=>'http://www.japundit.com',
'Jason Borneman'=>'http://www.xtra-rant.com/',
'Jax Blunt'=>'http://makingitup.blogspot.com/',
'Jay Cohen'=>'',
'Jayne Maxwell'=>'',
'Jeff Crossett'=>'http://www.crossedconnections.org/w/',
'Jennifer'=>'http://www.geeksmakemehot.com/',
'Jeremiah Cohick'=>'http://www.jeremiahlee.com/',
'Jessica Bennett'=>'http://accommodatingly.com/',
'Jewels Web Graphics'=>'http://www.jewelswebgraphics.com',
'Jim Galley'=>'http://blog.galley.net',
'Jla Min Chiou'=>'',
'Joan Llenas Maso'=>'http://www.joangarnet.com/',
'John Calnan'=>'http://www.calnan-web.com/weblog/',
'John Epperson'=>'',
'John Hartnup'=>'',
'John Robinson'=>'http://www.thebeard.org/weblog.html',
'JohnP'=>'http://johnp.co.nz',
'Jon Abad'=>'http://www.jonabad.com/',
'Jonathan Dingman'=>'http://www.soundlogic.us/',
'Jonathan L.'=>'http://jonlandrum.com/',
'Jose Sanchez'=>'http://www.sanchezconsulting.net/',
'Joseph G.'=>'http://monotonous.net',
'Joseph Geierman'=>'http://www.monotonous.net/',
'Josh Larios'=>'http://www.elsewhere.org/journal/',
'Joss'=>'http://www.jossmer.org.uk/blog/',
'Julian'=>'http://www.somethinkodd.com/oddthinking',
'Julio Ortiz'=>'http://www.julioangelortiz.net',
'K Mason-Northey'=>'',
'Karen'=>'http://www.fighting-breast-cancer.com/',
'Keith Constable'=>'http://kccricket.net/',
'Kelson Vibber'=>'http://www.hyperborea.org/journal/',
'Kenneth Brudzinski'=>'http://brudzinski.com/blog/',
'Kenneth Cooper'=>'',
'Kev Needham'=>'http://kev.needham.ca/blog/',
'Kev Needham'=>'http://mactips.info/blog/index.php',
'Kevin'=>'http://www.grinberg.ws/blog/',
'Kevin G.'=>'http://www.grinberg.ws/blog/',
'Kog Marketing'=>'http://www.kogmarketing.com/',
'L. M. O\'Donnell'=>'',
'LaMont Bankson, LLC'=>'',
'Larry Fransson'=>'http://www.subcritical.com/',
'Laura F Schomberg'=>'',
'Learning English'=>'http://www.englishcaster.com/bobrob/',
'Lee Sonko'=>'http://lee.org/blog/',
'Les Green'=>'http://www.greemo.com/blog/',
'Liberta'=>'http://www.libertini.net/liberta/',
'Lies Van Rompaey'=>'http://www.dailydog.be/',
'Life of Riley'=>'http://www.rileycat.com',
'Lisa Williams'=>'http://www.cadence90.com/wp/index.php',
'Loofah by the Inch'=>'http://www.loofahbytheinch.com/',
'MacManX'=>'http://www.macmanx.com/wordpress',
'Marc Bourassa'=>'http://marc-bourassa.com/',
'Marc Schulman'=>'http://americanfuture.net/',
'Marcus Ooi'=>'http://mooiness.com/',
'Maria'=>'http://intueri.org/',
'Maria Langer'=>'http://www.marialanger.com',
'Mario Witte'=>'http://www.chengfu.net/',
'Mark Coffey'=>'http://www.pkblogs.com/decision08/',
'Mark Nameroff'=>'',
'Mark Riley'=>'http://www.tamba2.org.uk/T2/',
'Martin Colello'=>'',
'Matt'=>'http://www.1115.org',
'Matt Scoville'=>'http://cupjoke.com',
'Matthew Mullenweg'=>'http://photomatt.net/',
'Matthew Scoville'=>'http://cupjoke.com/',
'Matthew Smith'=>'',
'MeeCiteeWurkor'=>'http://meeciteewurkor.com/wp/',
'Melanie'=>'http://itcouldbenothing.com/fruitfly/',
'Melissa Curlin'=>'',
'Michael Bishop'=>'http://www.miklb.com/blog/',
'Michael Erana'=>'http://g33k.efamilynj.org',
'Michael Gilmore'=>'',
'Michael Linder'=>'http://www.linder.com/',
'Michael Mace'=>'http://www.mikemace.com/',
'Michael Mayer'=>'',
'Michael Moncur'=>'http://www.figby.com/',
'Michael Thomsen'=>'http://www.blindmindseye.com',
'MichaelE'=>'http://www.bitweaver.org/me_g33k',
'Michel Dumais'=>'http://www.micheldumais.com/',
'Michel Valdrighi'=>'http://zengun.org/weblog/',
'Michel Vuijlsteke'=>'http://blog.zog.org/',
'Mike H.'=>'http://www.michaelhodges.com/wp/',
'Mike Johanson'=>'',
'Miklb'=>'http://www.miklb.com/',
'Modelhommes'=>'http://blog.girlboheme.com/',
'Mog'=>'http://www.mindofmog.net',
'Mr. Bill'=>'http://www.mrbill.net/',
'Ms. Underestimated'=>'http://www.msunderestimated.com/',
'Mulligan'=>'http://www.cantkeepquiet.com/',
'Nathan Lorenz'=>'http://www.radicalwacko.com/',
'Nathanael Boehm'=>'http://www.purecaffeine.com/',
'Nathaniel Stern'=>'http://nathanielstern.com/',
'Neuro'=>'http://www.eretzvaju.org/',
'Nicholas Meyer'=>'',
'Nicki'=>'http://www.nixit.co.nz/wordpress/',
'Nikkiana'=>'http://www.everytomorrow.org/',
'Nitin Pai'=>'http://acorn.nationalinterest.in/',
'Oliver White'=>'http://www.oliverwhite.me.uk/',
'Orange Fort'=>'http://blog.orangefort.com/',
'Orlando Vidali'=>'',
'Pamela McDermott'=>'http://dedanaan.com/',
'Paolo Brocco'=>'http://www.pbworks.net/',
'Paul Fischer'=>'http://addcast.net/wordpress/',
'Paul Mills'=>'',
'Paul Tomblin'=>'http://xcski.com/blogs/pt/',
'Pedro Timoteo'=>'http://tlog.dehumanizer.com',
'Pedro Vera-Perez'=>'http://veraperez.com/',
'Peter Buchholz'=>'',
'Peter Elst'=>'http://www.peterelst.com/blog/',
'Peter Gasston'=>'http://www.petergasston.co.uk',
'Peter Nacken'=>'http://nacken.com/',
'Petros Kolyvas'=>'http://opensores.shiftfocus.ca/',
'Pfish'=>'http://www.pfish.org',
'Phil Hodgen'=>'http://hodgen.com/',
'Phil Smith'=>'',
'PickleZone.com'=>'http://www.picklezone.com/mambo/',
'Pieterjan Lansbegen'=>'http://pjl.ath.cx',
'R. Marotta'=>'http://www.InTheMac.com',
'Rakesh Karan'=>'',
'RedNeck'=>'http://www.redneckramblings.com/',
'Rian Stockbower'=>'http://rianjs.net',
'Richard Silverstein'=>'http://www.richardsilverstein.com/tikun_olam/',
'Riley'=>'http://www.rileycat.com/',
'Rob Lund'=>'http://electrolund.com',
'Robbie C.'=>'http://urbangrounds.com/category/about/',
'Robert Gregory-Browne'=>'http://www.robertgregorybrowne.com/wordpress/',
'Robin Hooker'=>'http://hisnameisjimmy.com',
'Rodolpho Carrasco'=>'http://www.urbanonramps.com',
'Russ'=>'http://russ.innereyes.com',
'Ryan Waddell'=>'http://www.ryanwaddell.com/',
'S Garza'=>'',
'S. W. Anderson'=>'http://wpblog.ohpinion.com/',
'Sabine Kirstein'=>'',
'Sami Koykka'=>'http://www.pinseri.com/samik/',
'Samir Nassar'=>'http://steamedpenguin.com/',
'Saundra Mitchell'=>'',
'Scotfl'=>'http://scotfl.ca/',
'Scott Johnson'=>'http://myextralife.com/',
'Scott Reilly'=>'http://www.coffee2code.com/',
'Scott Winder'=>'',
'Sean Carroll'=>'http://cosmicvariance.com/',
'Sebastian Herp'=>'http://www.sebbi.de/',
'Secrex'=>'http://secrex.net/',
'Selig Landsman'=>'',
'Selma McCrory'=>'http://rosegarden.estirose.net/',
'Shabby Elements'=>'http://www.rigdonia.com/wp/',
'Shanti'=>'http://NinerNiner.com',
'Sharon Gartenberg'=>'http://pedestrianfriendly.com/',
'Sharon Howard'=>'http://www.earlymodernweb.org.uk/emn/',
'Sherwood Anderson'=>'',
'Simon Fleischmann'=>'http://up-load.com/',
'Stan Cook'=>'http://hawaiicop.com/',
'Stephan Lamprecht'=>'http://news.lamprecht.net/',
'Stephanie Booth'=>'http://climbtothestars.org/',
'Stephanie\'s Stuff'=>'http://www.stephanies-stuff.com/',
'Stephen McDonnell'=>'',
'Stephen Mugiri'=>'',
'Stephen Sherry'=>'http://www.marginalwalker.co.uk',
'Steve Rider'=>'http://steverider.org/',
'Steve Scaturan'=>'http://negimaki.com',
'Steven Ourada'=>'http://www.ourada.org/blog/',
'Stewart Russell'=>'http://scruss.com/blog/',
'Stone Cobra'=>'http://stonecobra.com/',
'Sujal Shah'=>'http://www.fatmixx.com',
'Surgical Strikes'=>'http://www.dantobindantobin.com/blog',
'Terry Dillard'=>'http://www.righttrack.us/about-me',
'Thomas Cloer'=>'http://www.teezeh.info',
'Thomas Marshall'=>'http://www.tvotw.co.uk',
'Thomas Rau'=>'http://www.herr-rau.de',
'Thomas Warnick'=>'',
'Tijl Kindt'=>'http://thequicky.net',
'Tim Pushman'=>'',
'Timothy West'=>'http://libertyforsale.com/',
'Tobias Klausmann'=>'http://schwarzvogel.de',
'Tony'=>'http://tony.brilliant-talkers.com/wp',
'Tracey the Keitai Goddess'=>'http://keitaigoddess.com',
'Troy Gustafson'=>'',
'Twidget'=>'http://charlesstricklin.com/',
'Viper007bond'=>'http://www.viper007bond.com',
'Walter Hutchens'=>'http://www.walterhutchens.net/blog/',
'Webslum Internet Services'=>'http://www.webslum.net/',
'Will Hines'=>'http://www.spitemag.com/',
'Will Howells'=>'http://www.willhowells.org.uk/blog/',
'William Koch'=>'http://youfailit.net/',
'Xavier Borderie'=>'http://xavier.borderie.net/blog/',
'Zasmine Donatello'=>'',
'antiorario'=>'http://orme.antiorario.it/',
'chapman godbey'=>'http://www.hawaiistories.com/eric/',
'cricket518'=>'',
'cve'=>'http://www.cvanepps.com',
'danweasel'=>'http://danweasel.com/',
'david chartier'=>'',
'david harris'=>'',
'dekay'=>'http://www.dekay.org/blog',
'drumandbass.de'=>'http://drumandbass.de',
'fouldsy'=>'http://www.fouldsy.com',
'get inside with me'=>'http://outside.org.uk',
'howard hall'=>'http://thesmedleylog.com/',
'jessamyn west'=>'http://jessamyn.info/',
'joshing'=>'http://joshing.org',
'mathrick'=>'http://mathrick.org',
'michael macioce'=>'',
'paul burgman'=>'http://www.press-photos.com/',
'war59312'=>'http://war59312.com',

);

	ksort($testers);
	$i = count($testers);
	foreach ($testers as $name => $url)
	{
		if ($url)
			echo "<a href=\"$url\">$name</a>";
		else
			echo $name;
		$i--;
		if ($i == 1)
			echo " & ";
		elseif ($i)
			echo ", ";
	
	}
?>
</p></fieldset>

<p style="text-indent:0;"><em><small><?php _e('Did I leave you out? please do let me know... I\'ve done my best to ensure I had the whole team covered, but I might have missed a few, so don\'t hesitate to <a href="http://unknowngenius.com/blog/wordpress/spam-karma/sk2-contact-form/">contact me</a>.', 'sk2'); ?></small></em></p>
</div>