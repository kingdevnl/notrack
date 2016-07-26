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
    default:
      alert("PauseNoTrack: Unknown action");
      return;
  }
  
  document.getElementById('centerpoint2').style.display = "none";
  document.getElementById("centerpoint1").style.display = "block";
  document.getElementById("fade").style.display = "block";
  
  if (Action == "pause" || Action == "start" || Action == "stop") {    
    document.forms["pause-form"].submit();
  }
  else {    
    document.forms["operation-form"].submit();
  }  
}
//Options Box--------------------------------------------------------
function ShowOptions() {
  document.getElementById('centerpoint2').style.display = "block";
  document.getElementById('fade').style.display = "block";
}
function HideOptions() {
  document.getElementById('centerpoint2').style.display = "none";
  document.getElementById('fade').style.display = "none";
}
