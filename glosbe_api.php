<?php

/**************************************************************
"Learning with Texts" (LWT) is free and unencumbered software 
released into the PUBLIC DOMAIN.

Anyone is free to copy, modify, publish, use, compile, sell, or
distribute this software, either in source code form or as a
compiled binary, for any purpose, commercial or non-commercial,
and by any means.

In jurisdictions that recognize copyright laws, the author or
authors of this software dedicate any and all copyright
interest in the software to the public domain. We make this
dedication for the benefit of the public at large and to the 
detriment of our heirs and successors. We intend this 
dedication to be an overt act of relinquishment in perpetuity
of all present and future rights to this software under
copyright law.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE 
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE
AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS BE LIABLE 
FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN 
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN 
THE SOFTWARE.

For more information, please refer to [http://unlicense.org/].
***************************************************************/

/**************************************************************
Call: glosbe_api.php?from=...&dest=...&phrase=...
      ... from=L2 language code (see Glosbe)
      ... dest=L1 language code (see Glosbe)
      ... phrase=... word or expression to be translated by 
                     Glosbe API (see http://glosbe.com/a-api)

Call Glosbe Translation API, analyze and present JSON results
for easily filling the "new word form"
***************************************************************/

##class ddeebbuugg
##{
##	function debug_log_entry( $xx ) {}
##	function debug_log( $level, $message ) {
##		echo( $message . "<br>\r\n" );
##	}
##};
##
##$debug_object = new ddeebbuugg();


// TODO: Move in the LWT settings
// TODO: Add your own yandex translation key (free)
$y_key = "";




$debtime = array();
$debtime[] = array('0',microtime(true));

require_once( 'settings.inc.php' );
require_once( 'connect.inc.php' );
require_once( 'dbutils.inc.php' );
require_once( 'utilities.inc.php' );
require_once( 'simple_html_dom.php' );

//$close_context = stream_context_create(array(
//	'http' => array('header'=>'Connection: close')
//));
$close_context = stream_context_create(array('http' => array('header'=>
'connection: close
accept: text/html
accept-encoding: gzip, deflate, br
accept-language: en-US,en;q=0.9,ru-RU;q=0.8,ru;q=0.7,de;q=0.6
cache-control: max-age=0
user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.108 Safari/537.36
')));
/************************************************************/
// ## Get all parameters

$from = trim(stripTheSlashesIfNeeded($_REQUEST["from"]));
$dest = trim(stripTheSlashesIfNeeded($_REQUEST["dest"]));
$custom = trim(stripTheSlashesIfNeeded($_REQUEST["custom"]));
$destorig = $dest;
$phrase = mb_strtolower(trim(stripTheSlashesIfNeeded($_REQUEST["phrase"])), 'UTF-8');

/************************************************************/
// ## Fetch translation and morph data

// ### Get stem and morph
//     Out: $org_stem - the most probable stem, $org_stems, 
//          $org_parse - the most probable morph, $org_parses, $org_spars[][] - morphs sorted by stems



$trmorph_url = 'http://coltekin.net/cagri/trmorph/index.php';
$context  = stream_context_create( array( 
  'http' => array(
       'header'  =>
'Accept: text/html
Accept-Language: en-US,en;q=0.9,ru-RU;q=0.8,ru;q=0.7,de;q=0.6
Cache-Control: max-age=0
Connection: close
Content-Type: application/x-www-form-urlencoded
Host: coltekin.net
Origin: http://coltekin.net
Referer: http://coltekin.net/cagri/trmorph/index.php
Upgrade-Insecure-Requests: 1
User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36',
       'method'  => 'POST',
       'content' => http_build_query(
	   array('word' => $phrase,'submit' => 'Analyze')
       ),
  )
));

$html = file_get_contents($trmorph_url, false, $context);

$debtime[] = array('trmorph fetch',microtime(true));

$html = str_get_html($html);
if( $html ) {
	$tran = $html->find("td.trmorph-demo-result",0);
}
	
$org_parses = array();
$org_stems = array();

if( $tran )
{
	preg_match_all( '/org\/wiki\/([^"]*)/', $tran->innertext, 
		$org_stems, PREG_PATTERN_ORDER );
	$org_stems = $org_stems[1]; // 0 is the array of full matches
	$org_stem = $org_stems[0]; // The most probable stem
	foreach( $tran->find("li") as $parse ) {
		$org_parses[] = $parse->plaintext;
	}
	$org_parse = $org_parses[0]; // The mos probable morph
	$org_spars = array(); // Sorted parses
	foreach( $org_parses as $mr ) {
		if( ! preg_match('/^([^&]+)(&lt;)?/',$mr, $stm) ) { continue; }
		$stm = $stm[1];
		if(! $org_spars[$stm] ) {
			$org_spars[$stm] = array();
		}
		$org_spars[$stm][] = $mr; // A morph
	}
	$c = new Collator('tr_TR'); // TODO: use $dest ?
	foreach( $org_spars as $stm => $mrs ) {
		// Sort the array of $stm's morphs by length
		usort($org_spars[$stm], function($a, $b) use(&$c) {
		    return mb_strlen($a) - mb_strlen($b) ? : ($c->compare($a,$b));
		});			
	}
}
else
{
	$org_stem = $phrase;
}

if( $html ) {
	$html->clear(); // memory leak according to the man
	unset($html);
}


$debtime[] = array('trmorph parse dom',microtime(true));

// ### Get Yandex-translate of the original text
//     Out: $trn_ynd

$y_u = "https://translate.yandex.net/api/v1.5/tr.json/translate" .               "?key=" . $y_key .
       "&lang=" . urlencode($from) . "-" . urlencode($dest) .
       "&format=plain" .
       "&text=" . urlencode($phrase);
$y_d = file_get_contents( $y_u, false, $close_context );
if(! ($y_d === FALSE) ) 
{
	$data = json_decode ($y_d, true);
	if ( isset($data['text']) ) 
	{
		$trn_ynd = join(", ", $data['text'] );
	}
}

$debtime[] = array('yandex',microtime(true));

// ### Prepare phrases (stems) mutations
//     Out: $org_alts, $phrase = the first stem
// 

if($custom != 'true') {
	$org_alts = array();

	if(count($org_stems) > 0){
		// NBA: leave only unique
		$org_alts = array_keys(array_flip($org_stems));
		rsort($org_alts);
		if(! in_array( $phrase, $org_alts ) ) {
			$org_alts[] = $phrase;
		}
		#$phrase = $alternatives[0]; //?
		$phrase = $org_stem;
	}
}


$debtime[] = array('muta',microtime(true));

// ### Prepare the translation array from glosbe
//     Out: $trn_data, $trn_ps, $html (!)
// 

$trn_url = 'https://glosbe.com/' . 
  urlencode($from) . '/' . urlencode($dest) . '/' . urlencode($phrase);

$html = file_get_contents( $trn_url, false, $close_context );

$debtime[] = array('glosbe fetch url',microtime(true));

$html = str_get_html( $html );

// TODO: handle not-found !

// $phrase => $trn_data
if( $html ) {
$trn_data = array();
foreach($html->find("li.phraseMeaning") as $tran) {
	$tr = array();
	$tr['tran'] = $tran->find("strong.phr",0)->plaintext;
	preg_match("/{\s*(\w*)[^}]*}/",
		$tran->find("div.gender-n-phrase",0)->plaintext,
		$ps );
	if( sizeof($ps) > 0 ){
		$tr['ps'] = trim($ps[1]);
	} else {
		$tr['ps'] = "";
	}

	$trn_data[] = $tr;
}

// Hack
$trn_ps = ""; $ps = array();
$meta = $html->find("div.defmetas",0)->plaintext;
if( $meta ) {
	preg_match("/\w*:\s*(\w+);/",$meta,$ps );
	if( sizeof($ps) > 0 ) {
		$trn_ps = trim($ps[1]);	
	} 
}

} // if( $html )


$debtime[] = array('glosbe parse dom',microtime(true));

/************************************************************/
// ## Create a page

// ### CSS for the glosbe text
$css = <<<CSS
.gender-n-phrase{color:gray;display:inline;font-style:italic;margin-left:8px}
.accents{color:gray;display:inline}
.examples{border-left:3px solid #d3d3d3;color:#2f4f4f;margin-left:1em;padding-left:1em;padding-top:3px}
.examples b{background-color:#ffe3bd;font-weight:400}
.user-avatar-box-name{color:gray;display:block;float:right;font-size:.5em;white-space:nowrap}
.text-info{color:#3a87ad}a.text-info:focus,a.text-info:hover{color:#2d6987}
.text-success{color:#468847}
a.text-success:focus,a.text-success:hover{color:#356635}
.text-left{text-align:left}
.text-right{text-align:right}
.text-center{text-align:center}
li.phraseMeaning {margin-top:10px}
}

CSS;

// ### Page header
pagestart_nobody('',$css);

// ### Scripts for pasting translations in the card 
//    Unfortunately it is not possible to hook it back to the head

?>
<script type="text/javascript">
//<![CDATA[
function addTranslation (en, tag, tr) {
	var w = window.parent.frames['ro'];
	if (typeof w == 'undefined') w = window.opener;
	if (typeof w == 'undefined') {
		alert ('Translation can not be copied!');
		return;
	}
	var enInput = w.document.forms[0].WoTranslation;
	var tagInputList = w.document.getElementById("termtags").getElementsByTagName("input");
	var tagInput = tagInputList[tagInputList.length - 1];
	var trInput = w.document.forms[0].WoRomanization;
	//debugger;
	if (typeof enInput != 'object' || typeof tagInput != 'object') {
		alert ('Translation can not be copied!');
		return;
	}
	var enOldValue = enInput.value;
	if (enOldValue.trim() == '') {
		enInput.value = en;
		w.makeDirty();
	}
	else {
		if (enOldValue.indexOf(en) == -1) {
			enInput.value = enOldValue + ' / ' + en;
			w.makeDirty();
		}
		else {
			if (confirm('"' + en + '" seems already to exist as a translation.\nInsert anyway?')) { 
				enInput.value = enOldValue + ' / ' + en;
				w.makeDirty();
			}
		}
	}

	var trOldValue = trInput.value;
	if (trOldValue.trim() == '') {
		trInput.value = tr;
		w.makeDirty();
	}

	var tagOldValue = tagInput.value;
	if (tagOldValue.trim() == '') {
		tagInput.value = tag;
		tagInput.focus();
		tagInput.blur();
		w.makeDirty();
	}
}

function addInfoToTranslation (info) {
	var w = window.parent.frames['ro'];
	if (typeof w == 'undefined') w = window.opener;
	if (typeof w == 'undefined') {
		alert ('Translation can not be copied!');
		return;
	}
	var enInput = w.document.forms[0].WoTranslation;
	//debugger;
	if (typeof enInput != 'object' ) {
		alert ('Translation can not be copied!');
		return;
	}
	var enOldValue = enInput.value;
	enInput.value = enOldValue + ' ' + info.toUpperCase();
	w.makeDirty();
}

function toLowerText () {
	var w = window.parent.frames['ro'];
	if (typeof w == 'undefined') w = window.opener;
	if (typeof w == 'undefined') {
		alert ('Translation can not be copied!');
		return;
	}
	var woText = w.document.forms[0].WoText;
	//debugger;
	if (typeof woText != 'object' ) {
		alert ('Translation can not be copied!');
		return;
	}
	woText.value = woText.value.toLowerCase();
	w.makeDirty();
}

function saveTranslation (en, tag, tr, level) {
	addTranslation(en, tag, tr);
	var w = window.parent.frames['ro'];
	var form = w.document.forms[0];
	form.querySelector("input[name=WoStatus]:checked").value = level;
	form.op.click();
}

function addRomanization (ro) {
	var w = window.parent.frames['ro'];
	if (typeof w == 'undefined') w = window.opener;
	if (typeof w == 'undefined') {
		alert ('Romanization can not be copied!');
		return;
	}
	var trInput = w.document.forms[0].WoRomanization;
	//debugger;
	if (typeof trInput != 'object') {
		alert ('Romanization can not be copied!');
		return;
	}

	if (trInput.value.trim() == '' || confirm('Replace romanization?')) {
		trInput.value = ro;
		w.makeDirty();
	} 
}


//]]>
</script>
<?php

// ### Shortcut to make New Term lowercase

echo '<span class="click" title="" '.
	'onclick="toLowerText();">'.
	'<img style="vertical-align: text-top;" '.
	     'src="icn/inbox-download.png" title="Term to lower" alt="ToLower" />'.
     "</span>\n&nbsp;";

// ### Header with all variants and a re-post field

foreach ($org_alts as $value) {
    echo '<a href="?custom=true&from=' . $from . '&dest=' . $dest . 
                  '&phrase=' . $value . '"> '.
	 '<span class=\"red2\">' . tohtml($value) . "</span></a>";
    echo '<span style="font-size:10px;">&nbsp;</span>';
    echo '<a target="_blank" href="https://glosbe.com/' . 
	  urlencode($from).'/'.urlencode($dest).'/'.urlencode($value).'">'.
	  '<img src="icn/book-open-bookmark.png" alt="Open in Glosbe"' . 
	        'style="vertical-align:text-bottom;" />' .
	 '</a>&nbsp;&nbsp';

}

echo '&nbsp;<img src="icn/arrow-circle-135.png" style="vertical-align:text-bottom;" />';
echo '&nbsp;<form style="display: inline-block;" action="" method="get">
<input type="text" name="phrase" maxlength="250" size="15" value="' . tohtml($phrase) . '">
<input type="hidden" name="from" value="' . tohtml($from) . '">
<input type="hidden" name="dest" value="' . tohtml($destorig) . '">
<input type="hidden" name="custom" value="true">
<input type="submit" style="display:none" value="reload">
</form>';

// ## Morphs and a machine translation

echo "<hr />\r\n"; 

echo '<table style="border:0px;" width="100%"><tr>' . "\r\n";

echo '<td width="50%">';
if( $trn_ynd ) {
	echo '<p>';
	echo '<span class="click" title="' . prepare_textdata_js($phrase) . '" '.
		'onclick="addTranslation(' . prepare_textdata_js($trn_ynd) . 
			"," . prepare_textdata_js($trn_ps) .
			"," . prepare_textdata_js(/*$phrase*/) . 
		');">'.
		'<img style="vertical-align: text-top;" '.
		     'src="icn/card--plus.png" title="Copy tran" alt="Copy" />'.
	     "</span>\n&nbsp;";
	echo $trn_ynd;
	echo "</p>\r\n";
}
echo "</td>\r\n";

echo '<td><span style="font-size: 0.7em">';
/**** Old style
echo '<p>';
foreach($org_parses as $gram) {
    echo '<span class="click" title="' . prepare_textdata_js($gram) . '" onclick="addRomanization(' . prepare_textdata_js($gram) . ');"><img style="vertical-align: text-bottom;" src="icn/card--plus.png" title="Copy rom" alt="Copy" /></span>' . "&nbsp;&nbsp;";
    // addInfoToTranslation
    $gram = preg_replace('/&lt;([^&]+)&gt;/','<a href="#" onclick="addInfoToTranslation('."'<".'$1'.">'".')">$0</a>',$gram);
    // error_log($gram."\n", 3, "/tmp/php.log");
    echo $gram;
    echo "<br />\r\n";
}
echo "</p>\r\n";
echo "<hr/>\r\n";
*************/
$parses = array();
$parses[] = $org_parse;
foreach($org_spars as $stm => $mrs) {
    foreach( array_slice($mrs,0,4) as $gram ) { // TODO: make a collapsible list of all morphs
	if( $gram == $org_parse ) { continue; }
	$parses[] = $gram;
    }
}

echo '<p>';
foreach($parses as $gram) {
	echo '<span class="click" title="' . prepare_textdata_js($gram) . '" onclick="addRomanization(' . prepare_textdata_js($gram) . ');"><img style="vertical-align: text-bottom;" src="icn/card--plus.png" title="Copy rom" alt="Copy" /></span>' . "&nbsp;&nbsp;";
        // addInfoToTranslation
        $gram = preg_replace('/&lt;([^&]+)&gt;/','<a href="#" onclick="addInfoToTranslation('."'<$1>'".')">$0</a>',$gram);
        echo $gram;
        echo "<br />\r\n";
}
echo "</p>\r\n";

echo "</span></td>\r\n";

echo "</tr></table>\r\n";

echo "<hr />\r\n"; 

/******** Not using a table yet


	echo '<p>';
	foreach( range(1,5) as $i ) {
		echo '<button class="click" style="padding: 0px;" onclick="saveTranslation(' . prepare_textdata_js($tran) . ',"",' . prepare_textdata_js($phrase) . ', ' . $i . ');"> ' . $i .  ' </button>' . "\n";
	}
	echo '<span class="click" title="' . prepare_textdata_js($phrase) . '" onclick="addTranslation(' . prepare_textdata_js($tran) . ',"",' . prepare_textdata_js($phrase) . ');"><img style="vertical-align: middle;" src="icn/tick-button.png" title="Copy" alt="Copy" /> &nbsp; ' . $tran . " " . $ps_dot . '</span><br />' . "\n";
	echo "</p>\r\n";

echo "&nbsp;<hr />\r\n"; 
*****************************/

// ## Cleanup glosbe html

$cleantags = array( "div.btn-group", "span.audioPlayer-container", "img", "div#simmilarPhrases", "div#translation-images", "article#translationExamples","footer","head","div.span3","div.breadcrumb","div.navbar","div.adContainer","header#pageHeader","script" );
foreach( $cleantags as $tag ) {
	foreach( $html->find($tag) as $elem ) {
		$elem->outertext = "";
	}
}	


$debtime[] = array('glosbe clean',microtime(true));

// ## Add hrefs to the Glosby text
foreach($html->find("li.phraseMeaning") as $tran) {
	$tr = $tran->find("strong.phr",0);
	preg_match("/{\s*(\w*)[^}]*}/",
		$tran->find("div.gender-n-phrase",0)->plaintext,
		$ps );
	if( sizeof($ps) > 0 ){
		$psd = trim($ps[1]);
	}
	if( $tr ) {
	    $trn_glsb = $tr->plaintext;
	    $tr->innertext = 
	      '<span class="click" title="' . prepare_textdata_js($phrase). '" ' . 
                  'onclick="addTranslation('.prepare_textdata_js($trn_glsb) .
                                           ",'".(($psd)?$psd:"")."'". 
                                           ','.prepare_textdata_js(/*$phrase*/). ');">'.
                '<img style="vertical-align: text-bottom;" '.
                  'src="icn/card--plus.png" title="Copy tran" alt="Copy" />&nbsp;'.
              '</span><span class="text-info">'.
                $trn_glsb.
              "</span>";
	}
}

foreach($html->find("h3") as $tran) {
	$tr = $tran->find("span",0);
	if( $tr ) {
		$orig = trim($tr->plaintext);
    		$new = '<a target="_blank" style="color:unset;" '.
		        'href="https://glosbe.com/' . 
	  		       urlencode($from).'/'.urlencode($dest).
			       '/'.urlencode($phrase).'">'.
			$orig."&nbsp".
	  		'<img src="icn/book-open-bookmark.png" '.
			     'alt="Open in Glosbe"' . 
	        	     'style="vertical-align:text-bottom;" />' .
	 		'</a>';
		$tr->innertext = $new;
		
	}
}


$debtime[] = array('glosbe beautify',microtime(true));

echo $html;


$html->clear(); 
unset($html);

/*


if ($from != '' && $dest != '' && $phrase != '') {

	$trn_url = 'http://tureng.com/en/' . urlencode($from) . '-' . urlencode($dest) . '/' . urlencode($phrase);
	shell_exec('wget "' . $trn_url . '" -O tureng.html');
	$html = file_get_html('tureng.html');
	$trn_data = array();
	// Find all translations
	foreach($html->find("table[id=englishResultsTable]") as $trn_table) {
		foreach($trn_table->find('tr') as $trn_row) {
			$trn_turkish_td_result = $trn_row->find('td.tr');
			$trn_english_td_result = $trn_row->find('td.en');
			if($trn_turkish_td_result && $trn_english_td_result) {
			    $trn_category_td = $trn_row->find('td')[1];
				$trn_translation = array(
					"category" => $trn_category_td->plaintext,
					"turkish" => $trn_turkish_td_result[0]->plaintext,
					"english_raw" => $trn_english_td_result[0]->plaintext
				);
				$reg_array = array();
				preg_match("/(.*)\s+(\w+)\./", $trn_translation["english_raw"], $reg_array);
				if ( (sizeof($reg_array) > 0) ) {
					$trn_translation["english"] = $reg_array[1];
					$trn_translation["ps"] = $reg_array[2];
				} else {
					$trn_translation["english"] = $trn_translation["english_raw"];
					$trn_translation["ps"] = "";
				}
				array_push($trn_data, $trn_translation);
			}		
		}
	}

	$ok = TRUE;
	
}

if ( $ok ) {

	if (count($trn_data) > 0) {
	
		$i = 0;

		echo "<p>\n";
		foreach ($trn_data as &$value) {
			$word = '';
			$ps_dot = '';
			$ps = '';
			$origin = '';
			if (isset($value['english'])) {
				$word = $value['english'];
			}
			if (isset($value['turkish'])) {
				$origin = $value['turkish'];
			} 
			if (isset($value['ps']) && $value['ps'] != "") {
				$ps_dot = $value['ps'] . ".";
				$ps = $value['ps'];
			} 
			if ($word != '') {
				$word = trim(strip_tags($word));
				echo '<button class="click" onclick="saveTranslation(' . prepare_textdata_js($word) . "," . prepare_textdata_js($ps) . "," . prepare_textdata_js($origin) . ', 1);"> 1 </button>' . "\n";
				echo '<button class="click" onclick="saveTranslation(' . prepare_textdata_js($word) . "," . prepare_textdata_js($ps) . "," . prepare_textdata_js($origin) . ', 2);"> 2 </button>' . "\n";
				echo '<button class="click" onclick="saveTranslation(' . prepare_textdata_js($word) . "," . prepare_textdata_js($ps) . "," . prepare_textdata_js($origin) . ', 3);"> 3 </button>' . "\n";
				echo '<button class="click" onclick="saveTranslation(' . prepare_textdata_js($word) . "," . prepare_textdata_js($ps) . "," . prepare_textdata_js($origin) . ', 4);"> 4 </button>' . "\n";
				echo '<button class="click" onclick="saveTranslation(' . prepare_textdata_js($word) . "," . prepare_textdata_js($ps) . "," . prepare_textdata_js($origin) . ', 5);"> 5 </button>' . "\n";
				echo '<span class="click" title="' . prepare_textdata_js($origin) . '" onclick="addTranslation(' . prepare_textdata_js($word) . "," . prepare_textdata_js($ps) . "," . prepare_textdata_js($origin) . ');"><img src="icn/tick-button.png" title="Copy" alt="Copy" /> &nbsp; ' . $word . " " . $ps_dot . '</span><br />' . "\n";
				$i++;
			}
		}
		echo "</p>";
		if ($i) {
		echo '<p>&nbsp;<br/>' . $i . ' translation' . ($i==1 ? '' : 's') . ' retrieved via <a href="http://tureng.com/" target="_blank">Tureng API</a>.</p>';
		}
		
	} else {
		
		echo '<p>No translations found (' . tohtml($from) . '-' . tohtml($dest) . ').</p>';
		
		if ($dest != "en" && $from != "en") {
		
			$ok = FALSE;
		
			$dest = "en";
			$titletext = '<a href="http://glosbe.com/' . $from . '/' . $dest . '/' . $phrase . '">Glosbe Dictionary (' . tohtml($from) . "-" . tohtml($dest) . "):  &nbsp; <span class=\"red2\">" . tohtml($phrase) . "</span></a>";
			echo '<hr /><p>&nbsp;</p><h3>' . $titletext . '</h3>';

			$glosbe_data = file_get_contents('http://glosbe.com/gapi/translate?from=' . urlencode($from) . '&dest=' . urlencode($dest) . '&format=json&phrase=' . urlencode($phrase));

			if(! ($glosbe_data === FALSE)) {

				$data = json_decode ($glosbe_data, true);
				if ( isset($data['phrase']) ) {
					$ok = (($data['phrase'] == $phrase) && (isset($data['tuc'])));
				}

			}

			if ( $ok ) {

				if (count($data['tuc']) > 0) {
	
					$i = 0;

					echo "<p>&nbsp;<br />\n";
					foreach ($data['tuc'] as &$value) {
						$word = '';
						if (isset($value['phrase'])) {
							if (isset($value['phrase']['text']))
								$word = $value['phrase']['text'];
						} else if (isset($value['meanings'])) {
							if (isset($value['meanings'][0]['text']))
								$word = "(" . $value['meanings'][0]['text'] . ")";
						}
						if ($word != '') {
							$word = trim(strip_tags($word));
							echo '<span class="click" onclick="addTranslation(' . prepare_textdata_js($word) . ');"><img src="icn/tick-button.png" title="Copy" alt="Copy" /> &nbsp; ' . $word . '</span><br />' . "\n";
							$i++;
						}
					}
					echo "</p>";
					if ($i) {
					echo '<p>&nbsp;<br/>' . $i . ' translation' . ($i==1 ? '' : 's') . ' retrieved via <a href="http://glosbe.com/a-api" target="_blank">Glosbe API</a>.</p>';
					}
		
				} else {
	
					echo '<p>&nbsp;<br/>No translations found (' . tohtml($from) . '-' . tohtml($dest) . ').</p>';
		
				}
	
			} else {

				echo '<p>&nbsp;<br/>Retrieval error (' . tohtml($from) . '-' . tohtml($dest) . '). Possible reason: There is a limit of Glosbe API calls that may be done from one IP address in a fixed period of time, to prevent from abuse.</p>';

			}
		}
	
	}
	
} else {

	echo '<p>Retrieval error (' . tohtml($from) . '-' . tohtml($dest) . '). Possible reason: There is a limit of Glosbe API calls that may be done from one IP address in a fixed period of time, to prevent from abuse.</p>';

}

*/


$debtime[] = array('end',microtime(true));

$dt_start = $debtime[0][1];
echo "</br>\r\n<table>";
foreach($debtime as $dt){
	echo "<tr><td>" . $dt[0] . "</td><td>" . ($dt[1]-$dt_start) . " sec </td></tr>\r\n";
}
echo "</table>\r\n";
pageend();

?>
