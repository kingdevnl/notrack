function PauseNoTrack(Action, PauseTime) {
  switch (Action) {
    case 'pause':
      document.getElementById("dialogmsg").innerHTML = "Pausing NoTrack for "+PauseTime+" minutes";
      break;
    case 'start':
      document.getElementById("dialogmsg").innerHTML = "Enabling NoTrack";
      break;
    case 'stop':
      document.getElementById("dialogmsg").innerHTML = "Disabling NoTrack";
      break;
    case 'restart':
      document.getElementById("dialogmsg").innerHTML = "Restarting System";
      break;
    case 'shutdown':
      document.getElementById("dialogmsg").innerHTML = "Shutting Down System";
      break;
    default:
      alert("PauseNoTrack: Unknown action");
      return;
  }
  
  document.getElementById('centerpoint2').style.display = "none";
  document.getElementById("centerpoint1").style.display = "block";
  document.getElementById("fade").style.display = "block";
  
  if (Action == "pause") {
    window.open("?a="+Action+PauseTime, "_self");
  }
  else {  
    window.open("?a="+Action, "_self");
  }  
}
/*var urlParams = <?php echo json_encode($_GET, JSON_HEX_TAG);?>;*/
//Options Box--------------------------------------------------------
function ShowOptions() {
  document.getElementById('centerpoint2').style.display = "block";
  document.getElementById('fade').style.display = "block";
}
function HideOptions() {
  document.getElementById('centerpoint2').style.display = "none";
  document.getElementById('fade').style.display = "none";
}
