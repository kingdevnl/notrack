//Supporting JavaScript for NoTrack stats.php
function CheckAkamai(Site) {
  if (/.*\.akamai\.net$|akamaiedge\.net$/.test(Site)) {
    return true;
  }
  return false;
}
//-------------------------------------------------------------------
function ValidateIPaddress(ipaddress) {
 if (/^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/.test(ipaddress)) {
    return true;
  }
  return false;
}
//-------------------------------------------------------------------
function ReportSite(Site, Remove) {  
  var Msg = "";
  var Block1 = "";
  var Block2 = "";
  var Report = "";
  var Split;
  var SplitLength = 0;
  
  Split = Site.split(".");
  SplitLength = Split.length
  
  if (SplitLength <= 1) {                        //1 or zero . isn't a valid URL
    Msg = "<p>Invalid site</p>";
  }
  else if (Site.substring(0, 1) == "*") {        //Give explination of strings starting with *
    Msg = "<p>Domains starting with * are known to utilise a large number of subdomains</p>";
  }
  else if (CheckAkamai(Site)) {                  //Is it an Akami site?
    Msg = "<p>Akami is a Content Delivery Network (CDN) providing media delivery for a wide range of websites.</p><p>It is more efficient to block the originating website, rather than an Akami subdomain.</p>";
  }
  else if (ValidateIPaddress(Site)) {            //Is it an IP Address
    Msg = "<p>Unable to Block IP addresses.<br />You could add it to your Firewall instead</p>";
  }
  else if (Remove) {                             //Difficult to deal with Whitelisting / Removing sites from BlackList, it requires a greater interaction with Data on the server
    Report= '<a class="button-blue" href="http://quidsup.net/notrack/report.php?site=remove--'+Site+'" target="_blank">Report</a> Request domain is removed from BlockList';
  }
  else {                                         //At this point we are dealing with Adding a site to BlackList
    Report = '<a class="button-blue" href="http://quidsup.net/notrack/report.php?site='+Site+'" target="_blank">Report</a> Report domain';
    
    if (SplitLength == 2) {                      //Single domain
      Block1 = '<a class="button-blue" href=" ./config.php?v=black&action=black&do=add&site='+Site+'&comment=" target="_blank">Block Domain</a> Add domain to your Black List';      
    }
    else {     
      if (SplitLength >= 3) {                    //Three or more splits maybe a .co domain
        if (Split[SplitLength-2] == "co") {      //Is it a .co domain?
  	  if (SplitLength == 3) {                //Single domain
	    Block1 = '<a class="button-blue" href=" ./config.php?v=black&action=black&do=add&site='+Site+'&comment=" target="_blank">Block Domain</a> Add domain to your Black List';
	  }
	  else if (SplitLength > 3) {            //Dealing with a .co domain with subdomains
	    Block1 = '<a class="button-blue" href=" ./config.php?v=black&action=black&do=add&site='+Split[SplitLength-3]+'.'+Split[SplitLength-2]+'.'+Split[SplitLength-1]+'&comment=" target="_blank">Block Domain</a> Block entire domain '+Split[SplitLength-3]+'.'+Split[SplitLength-2]+'.'+Split[SplitLength-1];
	    Block2 = '<a class="button-blue" href=" ./config.php?v=black&action=black&do=add&site='+Site+'&comment=" target="_blank">Block Subdomain</a> Add subdomain to your Black List';
	  }
        }
        else {                                   //Dealing with a non .co domain with subdomains
	  Block1 = '<a class="button-blue" href=" ./config.php?v=black&action=black&do=add&site='+Split[SplitLength-2]+'.'+Split[SplitLength-1]+'&comment=" target="_blank">Block Domain</a> Block entire domain '+Split[SplitLength-2]+'.'+Split[SplitLength-1];
	  Block2 = '<a class="button-blue" href=" ./config.php?v=black&action=black&do=add&site='+Site+'&comment=" target="_blank">Block Subdomain</a> Add subdomain to your Black List';
	}
      }
    }
  }
  
  //Modify DOM elements depending on whether a string has been set.
  document.getElementById("sitename").innerHTML = "<p>"+Site+"</p>";
  
  if (Msg == "") {
    document.getElementById("statsmsg").style.display = "none";
    document.getElementById("statsmsg").innerHTML = "";
  }
  else {
    document.getElementById("statsmsg").style.display = "block";
    document.getElementById("statsmsg").innerHTML = Msg;
  }
  
  if (Block1 == "") {
    document.getElementById("statsblock1").style.display = "none";
    document.getElementById("statsblock1").innerHTML = "";
  }
  else {
    document.getElementById("statsblock1").style.display = "block";
    document.getElementById("statsblock1").innerHTML = Block1;
  }
  
  if (Block2 == "") {
    document.getElementById("statsblock2").style.display = "none";
    document.getElementById("statsblock2").innerHTML = "";
  }
  else {
    document.getElementById("statsblock2").style.display = "block";
    document.getElementById("statsblock2").innerHTML = Block2;
  }
  
  if (Report == "") {
    document.getElementById("statsreport").style.display = "none";
    document.getElementById("statsreport").innerHTML = "";
  }
  else {
    document.getElementById("statsreport").style.display = "block";
    document.getElementById("statsreport").innerHTML = Report;
  }
  
  document.getElementById('fade').style.display = "block";
  document.getElementById('stats-center').style.display = "block";  
}
//-------------------------------------------------------------------
function HideStatsBox() {
  document.getElementById('stats-center').style.display = "none";
  document.getElementById('fade').style.display = "none";
}
//-------------------------------------------------------------------