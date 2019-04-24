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

require_once( 'settings.inc.php' );
require_once( 'connect.inc.php' );
require_once( 'dbutils.inc.php' );
require_once( 'utilities.inc.php' );
require_once( 'simple_html_dom.php' );

$from = trim(stripTheSlashesIfNeeded($_REQUEST["from"]));
$dest = trim(stripTheSlashesIfNeeded($_REQUEST["dest"]));
$custom = trim(stripTheSlashesIfNeeded($_REQUEST["custom"]));
$destorig = $dest;
$phrase = mb_strtolower(trim(stripTheSlashesIfNeeded($_REQUEST["phrase"])), 'UTF-8');
$ok = FALSE;
$trn_data = array();

{

	// Get all morphs

	$url = 'http://coltekin.net/cagri/trmorph/index.php';
	$data = array('word' => $phrase,'submit' => 'Analyze');
	$options = array(
            'http' => array(
	        'header'  =>
'Accept: text/html
Accept-Language: en-US,en;q=0.9,ru-RU;q=0.8,ru;q=0.7,de;q=0.6
Cache-Control: max-age=0
Connection: keep-alive
Content-Type: application/x-www-form-urlencoded
Host: coltekin.net
Origin: http://coltekin.net
Referer: http://coltekin.net/cagri/trmorph/index.php
Upgrade-Insecure-Requests: 1
User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36',
	        'method'  => 'POST',
	        'content' => http_build_query($data),
	    )
	);

	$context  = stream_context_create($options);
	$result = file_get_html($url, false, $context);
	$tran = $result->find("td.trmorph-demo-result",0);
	
	if( $tran )
	{
		// TODO: select a-href directly?
		// here is the first stem reference from the first parse
		preg_match( '/org\/wiki\/([^"]*)/', $tran->innertext, $stems );
		$stem = $stems[1];

		$parses = array();
		foreach( $tran->find("li") as $parse ) {
			array_push($parses,$parse->plaintext);
		}
	}

	$result->clear(); 
	unset($result);
}

// Prepare phrases (stems) mutations

if($custom != 'true') {
	$alternatives = array();
	$stems = array();	

    //TODO: add all possible stems
    
    array_push($stems, $stem );		

	if(count($stems) > 0){
		$alternatives = $stems;
		rsort($alternatives);
		// NBA: leave only unique
		$alternatives = array_keys(array_flip($alternatives));
		array_push($alternatives, $phrase);
		$phrase = $alternatives[0]; //?
	}
}


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

CSS;

pagestart_nobody('',$css);

//http://tureng.com/en/turkish-english/ucuz
$titletext = '<a href="http://tureng.com/en/' . $from . '-' . $dest . '/' . $phrase . '">Glosbe Dictionary (' . tohtml($from) . "-" . tohtml($dest) . "):  &nbsp; <span class=\"red2\">" . tohtml($phrase) . "</span></a>";
echo '<h3>' . $titletext . '</h3>';
// echo '<p>(Click on <img src="icn/tick-button.png" title="Choose" alt="Choose" /> to copy word(s) into above term)<br />&nbsp;</p>';

foreach ($alternatives as $value) {
	echo '<a href="tureng_api.php?custom=true&from=' . $from . '&dest=' . $dest . '&phrase=' . $value . '"> <span class=\"red2\">' . tohtml($value) . "</span></a>";
}

echo '&nbsp;=&gt;&nbsp;<form style="display: inline-block;" action="tureng_api.php" method="get">
<input type="text" name="phrase" maxlength="250" size="15" value="' . tohtml($phrase) . '">
<input type="hidden" name="from" value="' . tohtml($from) . '">
<input type="hidden" name="dest" value="' . tohtml($destorig) . '">
<input type="hidden" name="custom" value="true">
<input type="submit" style="display:none" value="Translate via Tureng">
</form>';

echo "&nbsp;<hr />\r\n"; 



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
	if (typeof enInput != 'object' || typeof tagInput != 'object') {
		alert ('Romanization can not be copied!');
		return;
	}

	if (trInput.value.trim() == '' || confirm('Replace romanization?')) {
		trInput.value = tr;
		w.makeDirty();
	} 
}


//]]>
</script>
<?php

echo '<span style="font-size: 0.875em">';
foreach($parses as $gram) {
    echo $gram . "&nbsp;";
    echo '<a onclick="addRomanization(' . prepare_textdata_js($gram) . ')">^</a>';
    echo "<br />\r\n";
}
echo '</span>';


echo "&nbsp;<hr />\r\n"; 


$trn_url = 'https://glosbe.com/' . urlencode($from) . '/' . urlencode($dest) . '/' . urlencode($phrase);


$html = file_get_html( $trn_url );
// TODO: handle not-found !

// $phrase => $tran
foreach($html->find("li.phraseMeaning") as $tran) {
	$tran = $tran->find("strong.phr",0)->plaintext;

	echo '<p>';
	foreach( range(1,5) as $i ) {
		echo '<button class="click" style="padding: 0px;" onclick="saveTranslation(' . prepare_textdata_js($tran) . ',"",' . prepare_textdata_js($phrase) . ', ' . $i . ');"> ' . $i .  ' </button>' . "\n";
	}
	echo '<span class="click" title="' . prepare_textdata_js($phrase) . '" onclick="addTranslation(' . prepare_textdata_js($tran) . ',"",' . prepare_textdata_js($phrase) . ');"><img style="vertical-align: middle;" src="icn/tick-button.png" title="Copy" alt="Copy" /> &nbsp; ' . $tran . " " . $ps_dot . '</span><br />' . "\n";
	echo "</p>\r\n";
}




echo "&nbsp;<hr />\r\n"; 

$html->find("head",0)->outertext = "";
$html->find("div.span3",0)->innertext = "";
$html->find("div.breadcrumb",0)->innertext = "";
$html->find("div.navbar",0)->innertext = "";
$html->find("div.adContainer",0)->innertext = "";
$html->find("header#pageHeader",0)->innertext = "";





$cleantags = array( "div.btn-group", "span.audioPlayer-container", "img", "div#simmilarPhrases", "div#translation-images", "article#translationExamples","footer" );
foreach( $cleantags as $tag )
{
	foreach( $html->find($tag) as $elem )
	{
		$elem->outertext = "";
	}
}	

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

pageend();

?>
