/********************************************************************
 *  Menu incognito
 *    POST Incognito operation to API
 *    API returns new status value
 *    Set incognito elements to purple colours
 *  Params:
 *    None
 *  Return:
 *    None
 */

function menuIncognito() {
  var oReq = new XMLHttpRequest();
  var url = "./include/api.php";
  var params = "operation=incognito";
  
  oReq.open("POST", url, true);
  oReq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
  /*oReq.onload = function () {
    // do something to response
    console.log(this.responseText);
  };*/
  oReq.onreadystatechange = function() {
    if(this.readyState == 4 && this.status == 200) {
      var apiResponse = JSON.parse(this.responseText);
      if (apiResponse["status"] & 8) {                     //Bitwise check for STATUS_INCOGNITO
        //Change incognito elements to purle colour
        document.getElementById("incognito-button").src = "./svg/menu_incognito_active.svg";
        document.getElementById("incognito-text").classList.add("purple");
      }
      else {
        //Turning incognito off, change incognito elements back to grey
        document.getElementById("incognito-button").src = "./svg/menu_incognito.svg";
        document.getElementById("incognito-text").classList.remove("purple");
      }
    }
  }
  oReq.send(params);
}


function PauseNoTrack(Action, PauseTime) {
  switch (Action) {
    case 'pause':
      document.getElementById("dialogmsg").innerHTML = "Pausing NoTrack for "+PauseTime+" minutes";
      document.getElementById("pause-time").value = "pause"+PauseTime;
      break;
    case 'start':
      document.getElementById("dialogmsg").innerHTML = "Enabling NoTrack";
      document.getElementById("pause-time").value = "start";      
      break;
    case 'stop':
      document.getElementById("dialogmsg").innerHTML = "Disabling NoTrack";
      document.getElementById("pause-time").value = "stop";      
      break;
    case 'force-notrack':
      document.getElementById("dialogmsg").innerHTML = "Updating Blocklists";
      document.getElementById("operation").value = "force-notrack";
      break;
    case 'restart':
      document.getElementById("dialogmsg").innerHTML = "Restarting System";
      document.getElementById("operation").value = "restart";
      break;
    case 'shutdown':
      document.getElementById("dialogmsg").innerHTML = "Shutting Down System";
      document.getElementById("operation").value = "shutdown";
      break;    
  }
  
  document.getElementById("fade").style.top=window.pageYOffset+"px";
  document.getElementById("fade").style.display = "block";
  
  document.getElementById("options-box").style.display = "none";
    
  document.getElementById("dialog-box").style.top = (window.pageYOffset + (window.innerHeight / 2))+"px";
  document.getElementById("dialog-box").style.left = (window.innerWidth / 2)+"px";
  document.getElementById("dialog-box").style.display = "block";
  
  if (Action == "pause" || Action == "start" || Action == "stop") {    
    document.forms["pause-form"].submit();
  }
  else {    
    document.forms["operation-form"].submit();
  }  
}
//Options Box--------------------------------------------------------
function ShowOptions() {  
  document.getElementById("fade").style.top=window.pageYOffset+"px";
  document.getElementById("fade").style.display = "block";
    
  document.getElementById("options-box").style.top = (window.pageYOffset + (window.innerHeight / 2))+"px";
  document.getElementById("options-box").style.left = (window.innerWidth / 2)+"px";
  document.getElementById("options-box").style.display = "block";
}
function HideOptions() {
  document.getElementById("options-box").style.display = "none";
  document.getElementById("fade").style.display = "none";
}
//-------------------------------------------------------------------
function openNav() {  
  if (typeof window.orientation !== 'undefined') {
    if (document.getElementById("menu-side").style.width == "0rem" || document.getElementById("menu-side").style.width == "") {
      document.getElementById("menu-side").style.width = "14rem";
      document.getElementById("main").style.marginLeft = "14rem";
    }
    else {
      document.getElementById("menu-side").style.width = "0rem";
      document.getElementById("main").style.marginLeft= "0rem"; 
    }
  }
  else {    
    if (document.getElementById("menu-side").style.width == "14rem" || document.getElementById("menu-side").style.width == "") {
      document.getElementById("menu-side").style.width = "3rem";
      document.getElementById("main").style.marginLeft= "3rem";
    }
    else {      
      document.getElementById("menu-side").style.width = "14rem";
      document.getElementById("main").style.marginLeft = "14rem";
    }
  }
}
