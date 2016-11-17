<?php

/********************************************************************
 *  Add Search Box String to SQL Search
 *
 *  Params:
 *    None
 *  Return:
 *    SQL Query string
 */
function add_searches() {
  global $blradio, $searchbox;
  $searchstr = '';
  
  if (($blradio != 'all') && ($searchbox != '')) {
    $searchstr = ' WHERE site LIKE \'%'.$searchbox.'%\' AND bl_source = \''.$blradio.'\' ';
  }
  elseif ($blradio != 'all') {
    $searchstr = ' WHERE bl_source = \''.$blradio.'\' ';
  }
  elseif ($searchbox != '') {
    $searchstr = ' WHERE site LIKE \'%'.$searchbox.'%\' ';
  }
  
  return $searchstr;
}


/********************************************************************
 *  Draw Blocklist Row
 *
 *  Params:
 *    Block list, bl_name, Message
 *  Return:
 *    None
 */
function draw_blocklist_row($bl, $bl_name, $msg) {
  global $Config, $CSVTld;
  //Txt File = Origniating download file
  //TLD Is a special case, and the Txt file used is $CSVTld
  
  $txtfile = false;
  $txtfilename = '';
  $txtlines = 0;
  $filename = '';
  $totalmsg = '';  
  
  if ($Config[$bl] == 0) {
    echo '<tr><td>'.$bl_name.':</td><td><input type="checkbox" name="'.$bl.'"> '.$msg.'</td></tr>'.PHP_EOL;
  }
  else {    
    $filename = strtolower(substr($bl, 3));
    if ($bl == 'bl_tld') {
      $txtfilename = $CSVTld;
    }
    else {
      $txtfilename = DIR_TMP.$filename.'.txt';
    }
    
    $rows = count_rows('SELECT COUNT(*) FROM blocklist WHERE bl_source = \''.$bl.'\'');
        
    $txtfile = file_exists($txtfilename);
    
    if (($rows > 0) && ($txtfile)) {
      $txtlines = intval(exec('wc -l '.$txtfilename));
      if ($rows > $txtlines) $rows = $txtlines;  //Prevent stupid result
      $totalmsg = '<p class="light">'.$rows.' used of '.$txtlines.'</p>';
    }
    else {
      $totalmsg = '<p class="light">'.$rows.' used of ?</p>';
    }
    
   
    echo '<tr><td>'.$bl_name.':</td><td><input type="checkbox" name="'.$bl.'" checked="checked"> '.$msg.' '.$totalmsg.'</td></tr>'.PHP_EOL;    
  }
    
  return null;
}


/********************************************************************
 *  Draw Blocklist Radio Form
 *    Radio list is made up of the items in $BLOCKLISTNAMES array
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function draw_blradioform() {
  global $BLOCKLISTNAMES, $showblradio, $blradio, $page, $searchbox;
  
  if ($showblradio) {                            //Are we drawing Form or Show button?
    echo '<form name = "blradform" method="GET">'.PHP_EOL;   //Form for Radio List
    echo '<input type="hidden" name="v" value="full" />'.PHP_EOL;
    echo '<input type="hidden" name="page" value="'.$page.'" />'.PHP_EOL;
    if ($searchbox != '') {
      echo '<input type="hidden" name="s" value="'.$searchbox.'" />'.PHP_EOL;
    }    
  
    if ($blradio == 'all') {
      echo '<span class="blradiolist"><input type="radio" name="blrad" value="all" checked="checked" onclick="document.blradform.submit()">All</span>'.PHP_EOL;
    }
    else {
      echo '<span class="blradiolist"><input type="radio" name="blrad" value="all" onclick="document.blradform.submit()">All</span>'.PHP_EOL;
    }
  
    foreach ($BLOCKLISTNAMES as $key => $value) { //Use BLOCKLISTNAMES for Radio items
      if ($key == $blradio) {                    //Should current item be checked?
        echo '<span class="blradiolist"><input type="radio" name="blrad" value="'.$key.'" checked="checked" onclick="document.blradform.submit()">'.$value.'</span>'.PHP_EOL;
      }
      else {
        echo '<span class="blradiolist"><input type="radio" name="blrad" value="'.$key.'" onclick="document.blradform.submit()">'.$value.'</span>'.PHP_EOL;
      }
    }
  }  
  else {                                         //Draw Show button instead
    echo '<form action="?v=full&amp;page='.$page.'" method="POST">'.PHP_EOL;
    echo '<input type="hidden" name="showblradio" value="1">'.PHP_EOL;
    echo '<input type="submit" class="button-blue" value="Select Block List">'.PHP_EOL;
  }
  
  echo '</form>'.PHP_EOL;                        //End of either form above
  echo '<br />'.PHP_EOL;
}

/********************************************************************
 *  Filter Config Post Request
 *
 *  Params:
 *    item - Post Item to check
 *  Return:
 *    1 if Post[item] = on, or 0 if not found
 */
function filter_config($item) {  
  if (isset($_POST[$item])) {    
    if ($_POST[$item] == 'on') {
      return 1;
    }
  }
  return 0;
}


/********************************************************************
 *  Load CSV List
 *    Load TLD List CSV file into $list
 *  Params:
 *    listname - blacklist or whitelist, filename
 *  Return:
 *    true on completion
 */
function load_csv($filename, $listname) {
  global $list, $mem;
    
  $list = $mem->get($listname);
  if (empty($list)) {
    $fh = fopen($filename, 'r') or die('Error unable to open '.$filename);
    while (!feof($fh)) {
      $list[] = fgetcsv($fh);
    }
    
    fclose($fh);
    if (count($list) > 50) {                     //Only store decent size list in Memcache
      $mem->set($listname, $list, 0, 600);       //10 Minutes
    }
  }
  
  return true;
}

/********************************************************************
 *  Load Custom Block List
 *    Loads a Black or White List from File into $list Array
 *    Saves $list into respective Memcache array  
 *  Params:
 *    listname - blacklist or whitelist, filename
 *  Return:
 *    true on completion
 */
function load_customlist($listname, $filename) { 
  global $list, $mem;
  
  $list = $mem->get($listname);
  
  if (empty($list)) {
    $fh = fopen($filename, 'r') or die('Error unable to open '.$filename);
    while (!feof($fh)) {
      $Line = trim(fgets($fh));
      
      if (Filter_URL_Str($Line)) {
        $Seg = explode('#', $Line);
        if ($Seg[0] == '') {
          $list[] = Array(trim($Seg[1]), $Seg[2], false);
        }
        else {
          $list[] = Array(trim($Seg[0]), $Seg[1], true);
        }        
      }
    }  
    fclose($fh);  
    $mem->set($listname, $list, 0, 60);
  }
  
  return true;  
}


/********************************************************************
 *  Load List
 *    Loads a a List from File and returns it in Array form
 *    Saves $list into respective Memcache array  
 *  Params:
 *    listname - blacklist or whitelist, filename
 *  Return:
 *    array of file
 */
function load_list($filename, $listname) {
  global $mem;
  
  $filearray = array();
  
  $filearray = $mem->get($listname);
  if (empty($filearray)) {
    if (file_exists($filename)) {                //Check if File Exists
      $fh = fopen($filename, 'r') or die('Error unable to open '.$filename);
      while (!feof($fh)) {
        $filearray[] = trim(fgets($fh));
      }
      fclose($fh);
      $mem->set($listname, $filearray, 0, 600);  //Change to 1800
    }
  }
  
  return $filearray;
}
/********************************************************************
 *  Show Advanced Page
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_advanced() {
  global $Config;
  echo '<form action="?v=advanced" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="advanced">';
  draw_systable('Advanced Settings');
  draw_sysrow('Suppress Domains <img class="btn" src="./svg/button_help.svg" alt="help" title="Group together certain domains on the Stats page">', '<textarea rows="5" name="suppress">'.str_replace(',', PHP_EOL, $Config['Suppress']).'</textarea>');
  echo '<tr><td colspan="2"><div class="centered"><input type="submit" class="button-grey" value="Save Changes"></div></td></tr>'.PHP_EOL;
  echo '</table>'.PHP_EOL;
  echo '</div></div>'.PHP_EOL;
  echo '</form>'.PHP_EOL;
}


/********************************************************************
 *  Show Block List Page
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_blocklists() {
  global $Config;

  echo '<form action="?v=blocks" method="post">';         //Block Lists
  echo '<input type="hidden" name="action" value="blocklists">';
  draw_systable('NoTrack Block Lists');
  draw_blocklist_row('bl_notrack', 'NoTrack', 'Default List, containing mixture of Trackers and Ad sites'); 
  draw_blocklist_row('bl_tld', 'Top Level Domain', 'Whole country and generic domains');
  draw_blocklist_row('bl_qmalware', 'Malware Sites', 'Malicious sites');
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Advert Blocking');
  draw_blocklist_row('bl_easylist', 'EasyList', 'EasyList without element hiding rules‎ <a href="https://forums.lanik.us/" target="_blank">(forums.lanik.us)</a>');
  draw_blocklist_row('bl_pglyoyo', 'Peter Lowe&rsquo;s Ad server list‎', 'Some of this list is already in NoTrack <a href="https://pgl.yoyo.org/adservers/" target="_blank">(pgl.yoyo.org)</a>'); 
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Privacy');
  draw_blocklist_row('bl_easyprivacy', 'EasyPrivacy', 'Supplementary list from AdBlock Plus <a href="https://forums.lanik.us/" target="_blank">(forums.lanik.us)</a>');
  draw_blocklist_row('bl_fbenhanced', 'Fanboy&rsquo;s Enhanced Tracking List', 'Blocks common tracking scripts <a href="https://www.fanboy.co.nz/" target="_blank">(fanboy.co.nz)</a>');
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Malware');
  draw_blocklist_row('bl_hexxium', 'Hexxium Creations Malware List', 'Hexxium Creations are a small independent team running a community based malware database <a href="https://hexxiumcreations.com/domain-ip-threat-database/" target="_blank">(hexxiumcreations.com)</a>');
  draw_blocklist_row('bl_cedia', 'CEDIA Malware List', 'National network investigation and education of Ecuador - Malware List <a href="https://cedia.org.ec/" target="_blank">(cedia.org.ec)</a>');
  draw_blocklist_row('bl_cedia_immortal', 'CEDIA Immortal Malware List', 'CEDIA Long-lived &#8220;immortal&#8221; Malware sites <a href="https://cedia.org.ec/" target="_blank">(cedia.org.ec)</a>');
  draw_blocklist_row('bl_disconnectmalvertising', 'Malvertising list by Disconnect', '<a href="https://disconnect.me/" target="_blank">(disconnect.me)</a>');
  draw_blocklist_row('bl_malwaredomainlist', 'Malware Domain List', '<a href="http://www.malwaredomainlist.com/" target="_blank">(malwaredomainlist.com)</a>');
  draw_blocklist_row('bl_malwaredomains', 'Malware Domains', 'A good list to add <a href="http://www.malwaredomains.com/" target="_blank">(malwaredomains.com)</a>');
  draw_blocklist_row('bl_spam404', 'Spam404', '<a href="http://www.spam404.com/" target="_blank">(www.spam404.com)</a>');
  draw_blocklist_row('bl_swissransom', 'Swiss Security - Ransomware Tracker', 'Protects against downloads of several variants of Ransomware, including Cryptowall and TeslaCrypt <a href="https://ransomwaretracker.abuse.ch/" target="_blank">(abuse.ch)</a>');
  draw_blocklist_row('bl_swisszeus', 'Swiss Security - ZeuS Tracker', 'Protects systems infected with ZeuS malware from accessing Command & Control servers <a href="https://zeustracker.abuse.ch/" target="_blank">(abuse.ch)</a>');
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Social');
  draw_blocklist_row('bl_fbannoyance', 'Fanboy&rsquo;s Annoyance List', 'Block Pop-Ups and other annoyances. <a href="https://www.fanboy.co.nz/" target="_blank">(fanboy.co.nz)</a>');
  draw_blocklist_row('bl_fbsocial', 'Fanboy&rsquo;s Social Blocking List', 'Block social content, widgets, scripts and icons. <a href="https://www.fanboy.co.nz" target="_blank">(fanboy.co.nz)</a>');
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Multipurpose');
  draw_blocklist_row('bl_someonewhocares', 'Dan Pollock&rsquo;s hosts file', 'Mixture of Shock and Ad sites. <a href="http://someonewhocares.org/hosts" target="_blank">(someonewhocares.org)</a>');
  draw_blocklist_row('bl_hphosts', 'hpHosts', 'Inefficient list <a href="http://hosts-file.net" target="_blank">(hosts-file.net)</a>');
  //draw_blocklist_row('bl_securemecca', 'Secure Mecca', 'Mixture of Adult, Gambling and Advertising sites <a href="http://securemecca.com/" target="_blank">(securemecca.com)</a>');
  draw_blocklist_row('bl_winhelp2002', 'MVPS Hosts‎', 'Very inefficient list <a href="http://winhelp2002.mvps.org/" target="_blank">(winhelp2002.mvps.org)</a>');
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Region Specific');
  draw_blocklist_row('bl_areasy', 'AR EasyList', 'EasyList Arab (عربي)‎ <a href="https://forums.lanik.us/viewforum.php?f=98" target="_blank">(forums.lanik.us)</a>');
  draw_blocklist_row('bl_chneasy', 'CHN EasyList', 'EasyList China (中文)‎ <a href="http://abpchina.org/forum/forum.php" target="_blank">(abpchina.org)</a>');
  
  draw_blocklist_row('bl_deueasy', 'DEU EasyList', 'EasyList Germany (Deutsch) <a href="https://forums.lanik.us/viewforum.php?f=90" target="_blank">(forums.lanik.us)</a>');
  draw_blocklist_row('bl_dnkeasy', 'DNK EasyList', 'Schacks Adblock Plus liste‎ (Danmark) <a href="https://henrik.schack.dk/adblock/" target="_blank">(henrik.schack.dk)</a>');  
  draw_blocklist_row('bl_fblatin', 'Latin EasyList', 'Spanish/Portuguese Adblock List <a href="https://www.fanboy.co.nz/regional.html" target="_blank">(fanboy.co.nz)</a>');
  
  draw_blocklist_row('bl_ruseasy', 'RUS EasyList', 'Russia RuAdList+EasyList (Россия Фильтр) <a href="https://forums.lanik.us/viewforum.php?f=102" target="_blank">(forums.lanik.us)</a>');
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Custom Block Lists');
  draw_sysrow('Custom', 'Use either Downloadable or Localy stored Block Lists<br /><textarea rows="5" name="bl_custom">'.str_replace(',', PHP_EOL,$Config['bl_custom']).'</textarea>');
  
  echo '</table><br />'.PHP_EOL;
  
  echo '<div class="centered"><input type="submit" class="button-grey" value="Save Changes"></div>'.PHP_EOL;
  echo '</div></div></form>'.PHP_EOL;
  
  return null;
}


/********************************************************************
 *  Show Custom List
 *    Follows on from Black List or White List being loaded
 *
 *  Params:
 *    $view - Current Config Page
 *  Return:
 *    None
 */
function show_custom_list($view) {
  global $list, $searchbox;
  
  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>'.ucfirst($view).' List</h5>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '<div class="sys-items">'.PHP_EOL;
  echo '<div class="centered">'.PHP_EOL;
  
  echo '<form action="?" method="get">';
  echo '<input type="hidden" name="v" value="'.$view.'">';
  if ($searchbox == '') echo '<input type="text" name="s" id="searchbox" placeholder="Search">'.PHP_EOL;
  else echo '<input type="text" name="s" id="searchbox" value="'.$searchbox.'">'.PHP_EOL;
  echo '</form>'.PHP_EOL;
  echo '</div></div></div>'.PHP_EOL;
  
  echo '<div class="sys-group">';
  echo '<div class="row"><br />'.PHP_EOL;
  echo '<table id="cfg-custom-table">'.PHP_EOL;
  $i = 1;

  if ($searchbox == '') {
    foreach ($list as $site) {
      if ($site[2] == true) {
        echo '<tr><td>'.$i.'</td><td>'.$site[0].'</td><td>'.$site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="changeSite(this)" checked="checked"><button class="button-small"  onclick="deleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
      }
      else {
        echo '<tr class="dark"><td>'.$i.'</td><td>'.$site[0].'</td><td>'.$site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="changeSite(this)"><button class="button-small"  onclick="deleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
      }
      $i++;
    }
  }
  else {
    foreach ($list as $site) {
      if (strpos($site[0], $searchbox) !== false) {
        if ($site[2] == true) {
          echo '<tr><td>'.$i.'</td><td>'.$site[0].'</td><td>'.$site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="changeSite(this)" checked="checked"><button class="button-small"  onclick="deleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
        }
        else {
          echo '<tr class="dark"><td>'.$i.'</td><td>'.$site[0].'</td><td>'.$site[1].'<td><input type="checkbox" name="r'.$i.'" onclick="changeSite(this)"><button class="button-small"  onclick="deleteSite('.$i.')"><span><img src="./images/icon_trash.png" class="btn" alt="-"></span></button></td></tr>'.PHP_EOL;
        }
      }
      $i++;
    }
  }
  
  echo '<tr><td>'.$i.'</td><td><input type="text" name="site'.$i.'" placeholder="site.com"></td><td><input type="text" name="comment'.$i.'" placeholder="comment"></td><td><button class="button-small" onclick="addSite('.$i.')"><span><img src="./images/green_tick.png" class="btn" alt=""></span>Save</button></td></tr>';
        
  echo '</table></div></div>'.PHP_EOL;
  
  echo '<div class="sys-group"><div class="centered">'.PHP_EOL;  
  echo '<a href="./include/downloadlist.php?v='.$view.'" class="button-grey">Download List</a>&nbsp;&nbsp;';
  echo '<a href="?v='.$view.'&amp;action='.$view.'&amp;do=update" class="button-blue">Update Blocklists</a>';
  echo '</div></div>'.PHP_EOL;  
}


/********************************************************************
 *  Show Domain List
 *    1. Load Users Domain Black list and convert into associative array
 *    2. Load Users Domain White list and convert into associative array
 *    3. Display list
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_domain_list() {
  global $list, $FileTLDBlackList, $FileTLDWhiteList;
    
  $KeyBlack = array_flip(load_list($FileTLDBlackList, 'TLDBlackList'));
  $KeyWhite = array_flip(load_list($FileTLDWhiteList, 'TLDWhiteList'));
  $listSize = count($list);
  
  if ($list[$listSize-1][0] == '') {             //Last line is sometimes blank
    array_splice($list, $listSize-1);            //Cut last line out
  }
  
  $FlagImage = '';  
  $UnderscoreName = '';

  echo '<div class="sys-group"><div class="sys-title">'.PHP_EOL;
  echo '<h5>Domain Blocking</h5>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '<div class="sys-items">'.PHP_EOL;
  echo '<span class="key key-red">High</span>'.PHP_EOL;
  echo '<p>High risk domains are home to a high percentage of Malicious sites compared to legitimate sites. Often they are cheap / free to buy and not well policed.<br />'.PHP_EOL;
  echo 'High risk domains are automatically blocked, unless you specifically untick them.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<span class="key key-orange">Medium</span>'.PHP_EOL;
  echo '<p>Medium risk domains are home to a significant number of malicious sites, but are outnumbered by legitimate sites. You may want to consider blocking these, unless you live in, or utilise the websites of the affected country.</p>'.PHP_EOL;
  echo '<p>e.g. .pl (Poland) domain used to house a large number of Exploit kits which sat short lived randomly named sites. Traditional blocking is impossible, therefore it can be safer to block the entire .pl domain.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<span class="key">Low</span>'.PHP_EOL;
  echo '<p>Low risk may still house some malicious sites, but they are vastly outnumbered by legitimate sites.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<span class="key key-green">Negligible</span>'.PHP_EOL;
  echo '<p>These domains are not open to the public, therefore extremely unlikely to contain malicious sites.</p>'.PHP_EOL;
  echo '<br />'.PHP_EOL;
  
  echo '<p><b>Shady Domains</b><br />'.PHP_EOL;
  echo 'Stats of &quot;Shady&quot; Domains have been taken from <a href="https://www.bluecoat.com/security/security-blog">BlueCoat Security Blog</a>. The definition of Shady includes Malicious, Spam, Suspect, and Adult sites.</p>';  
  
  echo '</div></div>'.PHP_EOL;
  
  
  //Tables
  echo '<div class="sys-group">'.PHP_EOL;
  if ($listSize == 0) {                          //Is List blank?
    echo 'No sites found in Block List'.PHP_EOL; //Yes, display error, then leave
    echo '</div>';
    return;
  }
  
  echo '<form name="tld" action="?" method="post">'.PHP_EOL;
  echo '<input type="hidden" name="action" value="tld">'.PHP_EOL;
    
  echo '<p><b>Old Generic Domains</b></p>'.PHP_EOL;
  echo '<table class="tld-table">'.PHP_EOL;
  
  foreach ($list as $site) {
    if ($site[2] == 0) {                         //Zero means draw new table
      echo '</table>'.PHP_EOL;                   //End old table
      echo '<br />'.PHP_EOL;
      echo '<p><b>'.$site[1].'</b></p>'.PHP_EOL; //Title of Table
      echo '<table class="tld-table">'.PHP_EOL;  //New Table
      continue;                                  //Jump to end of loop
    }
    
    switch ($site[2]) {                          //Row colour based on risk
      case 1: echo '<tr class="invalid">'; break;
      case 2: echo '<tr class="orange">'; break;      
      case 3: echo '<tr>'; break;                //Use default colour for low risk
      case 5: echo '<tr class="green">'; break;
    }
    
    $UnderscoreName = str_replace(' ', '_', $site[1]); //Flag names are seperated by underscore
    
    //Does a Flag image exist?
    if (file_exists('./images/flags/Flag_of_'.$UnderscoreName.'.png')) $FlagImage = '<img src="./images/flags/Flag_of_'.$UnderscoreName.'.png" alt=""> ';
    else $FlagImage = '';
    
    //(Risk 1 & NOT in White List) OR (in Black List)
    if ((($site[2] == 1) && (! array_key_exists($site[0], $KeyWhite))) || (array_key_exists($site[0], $KeyBlack))) {
      echo '<td><b>'.$site[0].'</b></td><td><b>'.$FlagImage.$site[1].'</b></td><td>'.$site[3].'</td><td><input type="checkbox" name="'.substr($site[0], 1).'" checked="checked"></td></tr>'.PHP_EOL;
    }
    else {
      echo '<td>'.$site[0].'</td><td>'.$FlagImage.$site[1].'</td><td>'.$site[3].'</td><td><input type="checkbox" name="'.substr($site[0], 1).'"></td></tr>'.PHP_EOL;
    }    
  }
  
  echo '</table>'.PHP_EOL;
  echo '<div class="centered"><br />'.PHP_EOL;
  echo '<input type="submit" class="button-grey" value="Save Changes">'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  echo '</form></div>'.PHP_EOL;          //End table
  
  return null;
}


/********************************************************************
 *  Show Full Block List
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_full_blocklist() {
  global $db, $page, $searchbox, $blradio, $showblradio;
  global $BLOCKLISTNAMES;
  
  $key = '';
  $value ='';
  $rows = 0;
  $row_class = '';
  $bl_source = '';
  $linkstr = '';
  
  echo '<div class="sys-group">'.PHP_EOL;
  echo '<h5>Sites Blocked</h5>'.PHP_EOL;
    
  $rows = count_rows('SELECT COUNT(*) FROM blocklist'.add_searches());
    
  if ((($page-1) * ROWSPERPAGE) > $rows) $page = 1;
    
  $query = 'SELECT * FROM blocklist '.add_searches().'ORDER BY id LIMIT '.ROWSPERPAGE.' OFFSET '.(($page-1) * ROWSPERPAGE);
  
  if(!$result = $db->query($query)){
    die('There was an error running the query'.$db->error);
  }
  
  draw_blradioform();
  
  echo '<form method="GET">'.PHP_EOL;            //Form for Text Search
  echo '<input type="hidden" name="page" value="'.$page.'" />'.PHP_EOL;
  echo '<input type="hidden" name="v" value="full" />'.PHP_EOL;
  echo '<input type="hidden" name="blrad" value="'.$blradio.'" />'.PHP_EOL;
  if ($searchbox == '') {                        //Anything in search box?
    echo '<input type="text" name="s" id="search" placeholder="Search">'.PHP_EOL;
  }
  else {                                         //Yes - Add it as current value
    echo '<input type="text" name="s" id="search" value="'.$searchbox.'">';
    $linkstr = '&amp;s='.$searchbox;             //Also add it to $linkstr
  }
  echo '</form></div>'.PHP_EOL;                  //End form
  
  
  if ($result->num_rows == 0) {                  //Leave if nothing found
    $result->free();
    echo 'No sites found in Block List'.PHP_EOL;
    echo '</div>';
    return false;
  }
  
  echo '<div class="sys-group">';                //Now for the results
  if ($showblradio) {
    pagination($rows, 'v=full'.$linkstr.'&amp;blrad='.$blradio);
  }
  else {
    pagination($rows, 'v=full'.$linkstr);
  }
  
  echo '<table id="block-table">'.PHP_EOL;
  echo '<tr><th>#</th><th>Block List</th><th>Site</th><th>Comment</th></tr>'.PHP_EOL;
   
  while($row = $result->fetch_assoc()) {         //Read each row of results
    
    if ($row['site_status'] == 0) {              //Is site enabled or disabled?
      $row_class = ' class="dark"';
    }
    else {
      $row_class = '';
    }
    
    if (array_key_exists($row['bl_source'], $BLOCKLISTNAMES)) { //Convert bl_name to Actual Name
      $bl_source = $BLOCKLISTNAMES[$row['bl_source']];
    }
    else {
      $bl_source = $row['bl_source'];
    }
    echo '<tr'.$row_class.'><td>'.$row['id'].'</td><td>'.$bl_source.'</td><td>'.$row['site'].'</td><td>'.$row['comment'].'</td></tr>'.PHP_EOL;
  }
  echo '</table>'.PHP_EOL;
  echo '</div>'.PHP_EOL;
  
  $result->free();

  return true;
}


/********************************************************************
 *  Show General View
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_general() {
  global $Config, $DirOldLogs, $SEARCHENGINELIST, $WHOISLIST;
  
  $key = '';
  $value = '';
  
  $sysload = sys_getloadavg();
  $freemem = preg_split('/\s+/', exec('free -m | grep Mem'));

  $pid_dnsmasq = preg_split('/\s+/', exec('ps -eo fname,pid,stime,pmem | grep dnsmasq'));

  $pid_lighttpd = preg_split('/\s+/', exec('ps -eo fname,pid,stime,pmem | grep lighttpd'));

  $Uptime = explode(',', exec('uptime'))[0];
  if (preg_match('/\d\d\:\d\d\:\d\d\040up\040/', $Uptime) > 0) $Uptime = substr($Uptime, 13);  //Cut time from string if it exists
  
  draw_systable('Server');
  draw_sysrow('Name', gethostname());
  draw_sysrow('Network Device', $Config['NetDev']);
  if (($Config['IPVersion'] == 'IPv4') || ($Config['IPVersion'] == 'IPv6')) {
    draw_sysrow('Internet Protocol', $Config['IPVersion']);
    draw_sysrow('IP Address', $_SERVER['SERVER_ADDR']);
  }
  else {
    draw_sysrow('IP Address', $Config['IPVersion']);
  }
  
  draw_sysrow('Sysload', $sysload[0].' | '.$sysload[1].' | '.$sysload[2]);
  draw_sysrow('Memory Used', $freemem[2].' MB');
  draw_sysrow('Free Memory', $freemem[3].' MB');
  draw_sysrow('Uptime', $Uptime);
  draw_sysrow('NoTrack Version', VERSION); 
  echo '</table></div></div>'.PHP_EOL;
  
  draw_systable('Dnsmasq');
  if ($pid_dnsmasq[0] != null) draw_sysrow('Status','Dnsmasq is running');
  else draw_sysrow('Status','Inactive');
  draw_sysrow('Pid', $pid_dnsmasq[1]);
  draw_sysrow('Started On', $pid_dnsmasq[2]);
  //draw_sysrow('Cpu', $pid_dnsmasq[3]);
  draw_sysrow('Memory Used', $pid_dnsmasq[3].' MB');
  draw_sysrow('Historical Logs', count_rows('SELECT COUNT(DISTINCT(DATE(log_time))) FROM historic').' Days');
  draw_sysrow('Delete All History', '<button class="button-danger" onclick="confirmLogDelete();">Purge</button>');
  echo '</table></div></div>'.PHP_EOL;

  
  //Web Server
  echo '<form name="blockmsg" action="?" method="post">';
  echo '<input type="hidden" name="action" value="webserver">';
  draw_systable('Lighttpd');
  if ($pid_lighttpd[0] != null) draw_sysrow('Status','Lighttpd is running');
  else draw_sysrow('Status','Inactive');
  draw_sysrow('Pid', $pid_lighttpd[1]);
  draw_sysrow('Started On', $pid_lighttpd[2]);
  //draw_sysrow('Cpu', $pid_lighttpd[3]);
  draw_sysrow('Memory Used', $pid_lighttpd[3].' MB');
  if ($Config['BlockMessage'] == 'pixel') draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" checked onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br /><input type="radio" name="block" value="message" onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br />');
  else draw_sysrow('Block Message', '<input type="radio" name="block" value="pixel" onclick="document.blockmsg.submit()">1x1 Blank Pixel (default)<br /><input type="radio" name="block" value="messge" checked onclick="document.blockmsg.submit()">Message - Blocked by NoTrack<br />');  
  echo '</table></div></div></form>'.PHP_EOL;

  
  //Stats
  echo '<form name="stats" method="post">';
  echo '<input type="hidden" name="action" value="stats">';
  
  draw_systable('Domain Stats');
  echo '<tr><td>Search Engine: </td>'.PHP_EOL;
  echo '<td><select name="search" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$Config['Search'].'">'.$Config['Search'].'</option>'.PHP_EOL;
  foreach ($SEARCHENGINELIST as $key => $value) {
    if ($key != $Config['Search']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;
  
  echo '<tr><td>Who Is Lookup: </td>'.PHP_EOL;
  echo '<td><select name="whois" onchange="submit()">'.PHP_EOL;
  echo '<option value="'.$Config['WhoIs'].'">'.$Config['WhoIs'].'</option>'.PHP_EOL;
  foreach ($WHOISLIST as $key => $value) {
    if ($key != $Config['WhoIs']) {
      echo '<option value="'.$key.'">'.$key.'</option>'.PHP_EOL;
    }
  }
  echo '</select></td></tr>'.PHP_EOL;  
  echo '</table></div></div></form>'.PHP_EOL;    //End Stats
  
  return null;
}

/********************************************************************
 *  Show Back End Status
 *
 *  Params:
 *    None
 *  Return:
 *    None
 */
function show_status() {
  echo '<pre>'.PHP_EOL;
  system('/usr/local/sbin/notrack --test');
  echo '</pre>'.PHP_EOL;
}

?>
