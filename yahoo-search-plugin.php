<?php
/*
Plugin Name: Yahoo! Search
Version: 1.0
Plugin URI: http://www.robinsonhouse.com/yahoo-search-plugin
Author: James E. Robinson, III
Author URI: http://www.robinsonhouse.com/
Description: Use Yahoo! Search Web Services for searching your content
*/
/*
 Copyright 2005 James E. Robinson, III (www.robinsonhouse.com)

 Redistribution and use in source and binary forms, with or without
 modification, are permitted provided that the following conditions are met:

 1. Redistributions of source code must retain the above copyright notice,
 this list of conditions and the following disclaimer.

 2. Redistributions in binary form must reproduce the above copyright notice,
 this list of conditions and the following disclaimer in the documentation
 and/or other materials provided with the distribution.

 3. The name of the author may not be used to endorse or promote products
 derived from this software without specific prior written permission.

 THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR IMPLIED
 WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO
 EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,
 PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS;
 OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
 WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR
 OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
 ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

$ys_service = 'http://api.search.yahoo.com/WebSearchService/V1/webSearch';
// Full Spec @ http://developer.yahoo.net/search/web/V1/webSearch.html

$ys_options = array();
$ys_optionsName = 'ys_options';
$ys_optionsKeys = array('appid', 'site', 'type', 
                       'format', 'language', 'results');

$ys_optionsVals['appid']['length'] = array(8, 40);
$ys_optionsVals['site']['length'] = array(3, 256);
$ys_optionsVals['type']['array'] = array('all', 'any', 'phrase');
$ys_optionsVals['format']['array'] = array('any', 'html');
$ys_optionsVals['language']['length'] = array(2, 3);
$ys_optionsVals['results']['array'] = array('5', '10', '15', '20',
                                       '25', '50', '75', '100');
$ys_errInfo = "";

// on first run, setup some sane default options to get things going
function ys_firstRun() {
   global $ys_optionsName;

   $url = get_bloginfo('url');
   $parts = parse_url($url);
   $site = $parts['host'];

   $options = array( 'appid' => 'YahooDemo',
                     'type' => 'all',
                     'results' => 10,
                     'format' => 'html',
                     'language' => 'en',
                     'site' => $site );

   $desc = "Options for Yahoo! Search plugin";

   // autoload off since we only need options if searching
   $autoload = 'no';

   add_option($ys_optionsName, $options, $desc, $autoload);

   return;
}

function ys_loadOptions() {
   global $ys_options, $ys_optionsName;

   $ys_options = get_option($ys_optionsName);

   if ( $ys_options  === false) {
      ys_firstRun();
      $ys_options = get_option($ys_optionsName);
   }

   return;
}

// hi-tech xml parsing
function ys_getTag($xml, $tag) {
   $preg = "/<".$tag.">(.*?)<\/".$tag.">/si";
   preg_match_all($preg, $xml, $matches);
   foreach ( $matches[1] as $value ) {
      $tags[] = $value;
   }
   return $tags;
} 

// more hi-tech xml parsing
function ys_getAttr($xml, $attr) {
   $preg = '/' . $attr . '\W*=\W*(\d+)/i';
   preg_match($preg, $xml, $matches);
   $attrs = $matches[1];
   return $attrs;
}

// format the yahoo query from what we know
function ys_buildQuery() {
   global $ys_service, $ys_options;
   
   // only load when needed
   ys_loadOptions();

   $q == False;

   if ( isset($_REQUEST['query']) ) {
      $q = $ys_service . '?query='
         . rawurlencode($_REQUEST['query']);

      if ( ! empty($_REQUEST['start']) ) {
         $q .= "&start=" . $_REQUEST['start'];
      }

      foreach ($ys_options as $key => $value) {
         $q .= "&$key=$value";
      }
   }

   return $q;
}

// handle paging
function ys_nextPrev($total, $start, $last) {
   global $ys_options;

   $resultspp = $ys_options['results'];
   $encquery = rawurlencode($_REQUEST['query']);

   if($start > 1) {
      echo '<a href="?query=' . $encquery
         . '&start=' . ($start - $resultspp) 
         . '">&laquo; Previous Page</a>';
   }

   echo " &#8212; ";

   if( $last < $total ) {
      echo '<a href="?query=' . $encquery
         . '&start=' . ($last + 1) . '">Next Page &raquo;</a>';
   }

   return;
}

// get it and print it
function ys_showResults() {
   $query = ys_buildQuery();
   $xml = file_get_contents($query);

   $arTitle   = ys_getTag($xml, 'Title');
   $arSummary = ys_getTag($xml, 'Summary');
   $arUrl     = ys_getTag($xml, 'Url');
   $arClick   = ys_getTag($xml, 'ClickUrl');
   $arMod     = ys_getTag($xml, 'ModificationDate');

   $first     = ys_getAttr($xml, 'firstResultPosition');
   $returned  = ys_getAttr($xml, 'totalResultsReturned');
   $total     = ys_getAttr($xml, 'totalResultsAvailable');

   if ( $returned ) {
      $last = $first + $returned - 1;
   } else {
      $last = 0;
   }

   echo "<h1>Found $total results, showing $first to $last</h1>\n";

   $dateFormat = get_settings('date_format');
   $timeFormat = get_settings('time_format');
   for ( $i = 0; $i < $returned; $i++) {
      $title = $arTitle[$i];
      $summary = $arSummary[$i];
      $url = $arUrl[$i];
      $clickurl = $arClick[$i];
      $lmod = $arMod[$i];
      $lastmodified = date("$dateFormat \\a\\t $timeFormat", $lmod);

      echo <<<RESULT
         <div class="post">
         <h2><a href="$clickurl" title="$title">$title</a></h2>
            <div class="entry">
            $summary
            </div>
         <p class="postmetadata">Modified $lastmodified</p>
         </div>
RESULT;
   }

   ys_nextPrev($total, $first, $last);

   return;
}

function yahooSearchForm() {
   $query = "";
   $showresults = 0;

   if ( isset($_REQUEST['query']) ) {
      $query = rawurldecode($_REQUEST['query']);
      $showresults = 1;
   }

   // The "Powered by..." text is required by the TOS, you did read the TOS
   // didn't you?  Everyone's doing it; you should to.
   echo <<<SEARCH1
      <h1>Powered by <a href="http://search.yahoo.com/" title="Yahoo! Search">Yahoo! Search</a></h1>
      <form id="searchform" method="get">
      <fieldset>
         <label for="ys">Enter Search Terms <input type="text" name="query" id="ys" size="25" alt="search terms" value="$query" tabindex="1" /></label>
         <input type="submit" name="submit" value="Go" tabindex="2" />
      </fieldset>
      </form>
SEARCH1;

   if ( $showresults ) {
      ys_showResults();
   }

   return;
}

function ys_adminPanel() {

   if ( function_exists('add_options_page') ) {
      add_options_page('Yahoo! Search', 'Yahoo! Search',
                           9, basename(__FILE__),
                           'ys_optionsSubpanel');
   }

   return;
}

// print the given array index as options for <select>
function ys_showAdminOptions($name) {
   global $ys_options, $ys_optionsVals;

   $current = $ys_options[$name];
   $values = $ys_optionsVals[$name]['array'];

   foreach ( $values as $value ) {
      if ( $value == $current ) {
         echo '<option value="' . $value . '" selected>' 
            . $value . '</option>';
      } else {
         echo '<option value="' . $value . '">' 
            . $value . '</option>';
      }
   }
   return;
}

// keepin' it real
function ys_validateInput() {
   global $ys_optionsVals, $ys_optionsKeys, $ys_errInfo;
   $ar = array();

   foreach ( $ys_optionsKeys as $key ) {
      if ( empty($_POST[$key]) ) {
         return false;
      }
   }

   $valid = 0;
   foreach ( $ys_optionsKeys as $key ) {
      $optVals = $ys_optionsVals[$key];
      $optType = key($optVals);
      $optValue = $optVals[$optType];

      $input = $_POST[$key];

      switch ($optType) {
         case 'length':
            list($min, $max) = $optValue;
            $len = strlen($input);
            if ( $len <= $max && $len >= $min ) {
               $ar[$key] = $input;
               $valid++;
            } else {
               $ys_errInfo .= "Option $key has incorrect length. <br />";
            }
            break;
         case 'array':
            if ( in_array($input, $optValue) ) {
               $ar[$key] = $input;
               $valid++;
            } else {
               $ys_errInfo .= "Option $key has invalid value. <br />";
            }
            break;
      }
   }

   if ( count($ys_optionsKeys) != $valid ) {
      $ar = false;
   }

   return $ar;
}

function ys_optionsUpdate() {
   global $ys_optionsName, $ys_options;

   $newOptions = ys_validateInput();

   if ( $newOptions === false ) {
      $rc = false;
   } else {
      update_option($ys_optionsName, $newOptions);
      ys_loadOptions();
      $rc = true;
   }

   return $rc;
}

function ys_optionsSubpanel() {
   global $ys_options, $ys_errInfo;

   ys_loadOptions();

   if (isset($_POST['info_update'])) {
      echo '<div class="updated"><p><strong>';

      $rc = ys_optionsUpdate();

      if ( $rc === false ) {
         echo "Sorry, failed to update options: $ys_errInfo";
      } else {
         echo "Options updated successfully.\n";
      }

      echo "</strong></p></div>\n";
   }

   echo <<<PANEL2
   <div class="wrap">
      <h2>Yahoo! Search Options</h2>
         <form method="post">
         <fieldset class="options" name="set1">
            <legend>Search Configuration</legend>
            <table width="100%" cellspacing="2" cellpadding="5" class="editform">
            <tr valign="top">
               <th scope="row" width="33%">Domain Name: </th>
               <td><input name="site" id="site" type="text" size="40" value="${ys_options['site']}" /> <br /> A domain or hostname, <strong>not</strong> a URL nor a URI<br /> You should not need to change this value.</td>
            </tr>
            <tr valign="top">
               <th scope="row">Application ID: </th>
               <td><input name="appid" id="appid" type="text" size="40" value="${ys_options['appid']}" /> <br /> <a target="_new" href="http://api.search.yahoo.com/webservices/register_application" title="Yahoo! Application ID Request"> Request a Yahoo! appid</a> (valid Yahoo! userid required) </td>
            </tr>
            </table>
         </fieldset>
         <fieldset class="options" name="set2">
            <legend>Search Options</legend>
            <table width="100%" cellspacing="2" cellpadding="5" class="editform">
            <tr valign="top">
               <th scope="row" width="33%">Results Per Page: </th>
               <td>
               <select name="results" id="results">
PANEL2;
   ys_showAdminOptions('results');
   echo <<<PANEL3
               </select> <br />
                     default 10, 100 max.
               </td>
            </tr>
            <tr valign="top">
               <th scope="row">Search Type: </th>
               <td><select name="type" id="type">
PANEL3;
   ys_showAdminOptions('type');
   echo <<<PANEL4
                    </select><br />
                    Match <strong>all</strong> words, match <strong>any</strong> word, or match words as a <strong>phrase</strong>.
                    </td>
            </tr>
            <tr valign="top">
               <th scope="row">Format: </th>
               <td><select name="format" id="format">
PANEL4;
   ys_showAdminOptions('format');
   echo <<<PANEL5
                     </select> <br /> Specifies the kind of files/data to search. <br /> Use <strong>html</strong> to search only your blog content, use <strong>any</strong> to include additional content like text files or PDF documents.  </td>
            </tr>
            <tr valign="top">
               <th scope="row">Language: </th>
               <td><input name="language" id="language" type="text" size=4 value="${ys_options['language']}" /> <br /> Language in which to display results.  <a href="http://developer.yahoo.net/search/languages.html" title="Yahoo! Supported Languages">(complete list)</a> </td>
            </tr>
            </table>
         </fieldset>
         <div class="submit">
         <input type="submit" name="info_update" value="Update options" />
         </div>
      </form>
   </div>
PANEL5;
   return;
}

add_action('admin_menu', 'ys_adminPanel');

?>
