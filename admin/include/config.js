function getUrlVars() {
  var vars = {};
  var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi,    
  function(m,key,value) {
    vars[key] = value;
  });
  return vars;
}

function ConfirmLogDelete() {
  if (confirm("Are you sure you want to delete all History?")) window.open("?action=delete-history", "_self");
}
function AddSite(RowNum) {  
  var SiteName = document.getElementsByName('site'+RowNum)[0].value;
  var Comment = document.getElementsByName('comment'+RowNum)[0].value;
  window.open('?v='+getUrlVars()["v"]+'&action='+getUrlVars()["v"]+'&do=add&site='+SiteName+'&comment='+Comment, "_self");
}
function DeleteSite(RowNum) {
  window.open('?v='+getUrlVars()["v"]+'&action='+getUrlVars()["v"]+'&do=del&row='+RowNum, "_self");
}
function ChangeSite(Item) {
  window.open('?v='+getUrlVars()["v"]+'&action='+getUrlVars()["v"]+'&do=cng&row='+Item.name.substring(1)+'&status='+Item.checked, "_self");  
}
