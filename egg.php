<?php
 
$gpxDirectory="/Users/nkonopinski/GPX.back/";
// log in to your account with a browser and update these cookie values to access more log entries and archived caches
$gspkuserid="";
$ASPdotNET_SessionId="";


//get listing of all GPX files in the directory
if ($handle = opendir($gpxDirectory)) {
    while (false !== ($file = readdir($handle))) {
    if(preg_match("#^GC.*?\.GPX#i",$file)){
            $files[]= $file;
        }
    }
    closedir($handle);
}
 
echo "\nUpdating hints, descriptions, and logs for GPX files in ${gpxDirectory}\n";
echo "======================================================================================================\n";
foreach($files as $file){
  echo "${file} : ";
 
  // clear variables on each loop
  unset($longDesc,$shortDesc,$hintEncrypt,$hintKeys,$hintDecrypt,$logs,$logEntries);
  unset($travelBugs,$travelBugUrls, $bugContents, $bugDetails,$bugGpx);
 
  // load gpx file
  $xml = simplexml_load_file($gpxDirectory.$file);
 
  // read cache data
  $data = $xml->wpt->children("http://www.groundspeak.com/cache/1/0");

  // load page from geoaching.com
  $url=$xml->wpt->url."&log=y&decrypt=y";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array("Cookie: gspkuserid=${gspkuserid}; Cookie: ASP.NET_SessionId=${ASPdotNET_SessionId};"));
  $urlContents = curl_exec($ch);
  curl_close($ch);

  if ( strpos($urlContents, "but is available for viewing for archival purposes") ){
    echo "archived cache. ";
  } else if ( strpos($urlContents, "This cache listing has been archived") ) {
    echo "archived cache. Must be logged in to view. Skipping\n";
    continue;
  } else if ( strpos($urlContents, "has chosen to make this cache listing visible to Premium Members only")) {
    echo "locked down to premium members. Skipping\n";
    continue;
  }

  $dom = new DOMDocument;
  $dom->validateOnParse = true;
  @$dom->loadHTML($urlContents); // @ means supress any warnings generated validating xml

  // parse out desired information
  $xpath = new DOMXPath($dom);
  $longDesc = $xpath->query("//*[@id='ctl00_ContentBody_LongDescription']")->item(0)->nodeValue;
  $shortDesc = $xpath->query("//*[@id='ctl00_ContentBody_ShortDescription']")->item(0)->nodeValue;
  $hint = $xpath->query("//*[@id='div_hint']")->item(0)->nodeValue;
 
  // clean up the text a bit
  $shortDesc=strip_tags(trim($shortDesc));
  $longDesc=strip_tags(trim($longDesc));
  $hintDecrypt=strip_tags(trim($hint));
 
  // save new values in xml
  $data->cache->short_description=$shortDesc;
  $data->cache->long_description=$longDesc;
  $data->cache->encoded_hints=$hint;
 
  // get log entries. groundspeak only gives the 5 most recent for non-logged in users at this point
  preg_match("#initalLogs\s?=\s?({.*);#", $urlContents, $logJSON);
  $json = json_decode($logJSON[1]);
  $i=0;
  foreach ($json->data as $logEntry){
    @$logDate=date('Y-m-d',strtotime($logEntry->Visited))."T00:00:00"; //need to convert 02/09/2009 format to 2009-02-09T20:00:00
    $data->cache->logs->log[$i]->date   = $logDate;
    $data->cache->logs->log[$i]->type   = $logEntry->LogType;
    $data->cache->logs->log[$i]->finder = $logEntry->UserName;
    $data->cache->logs->log[$i]->text   = strip_tags(trim($logEntry->LogText));
    $i++;
  }
 
  // write new data to file
  file_put_contents($gpxDirectory.$file,$xml->asXML());
  echo "updated\n";
}