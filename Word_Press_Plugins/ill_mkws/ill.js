

function formSubmit() {
	var catFilterQueryParam = "";
	if (document.search.category_filter) {
		catFilterQueryParam = 
			"&categoryfilter=" + encodeURIComponent(document.search.category_filter.value);
	}
    if (document.search.query.value) {       
      document.location = "https://senylrc.indexdata.com/search.html?" + 
        "query=" + encodeURIComponent(document.search.query.value) +
        catFilterQueryParam +
        "&sort=" + "&perpage=" + "&torusquery=";
    }
}

function saveUserSelections() {
	var setts = {};    
	controlRegister.populateArray(setts);
	saveSettings(setts);	
}
function auth_check(methods) {
    auth.check(indexMain, function () { window.location = "https://senylrc.indexdata.com/search.html/login.html";}, methods );
}

function indexMain() {
    mk_showPage();
    if (auth.indexCss && document.getElementById("stylesheet")) {
        document.getElementById("stylesheet").href = auth.indexCss;
    }        
    loadComponents();
    renderComponent("authInfoComp", auth);
    renderComponent("authLogoComp", auth);
    controlRegister.loadControls();
    loadSettings(function (setts) {
        controlRegister.populateControls(setts);      
    });
    document.search.query.focus();    
}

/*For ILL article request question*/
function yesnoCheck() {
    if (document.getElementById('yesCheck').checked) {
        document.getElementById('ifYes').style.display = 'block';
    }
    else document.getElementById('ifYes').style.display = 'none';

}

/*For ILL article request question*/
function yesnoCheck2() {
    if (document.getElementById('yesCheck').checked) {
        document.getElementById('ifYes').style.display = 'block';
    }
    else document.getElementById('ifYes').style.display = 'none';

}



/*for ILL DIRcotr page */
function showHide(elem) {
    var x = document.getElementById('showhide-'+elem);
    if (x.style.display === 'none') {
        x.style.display = 'block';
    } else {
        x.style.display = 'none';
    }
}


function multiRequest() {
        var list = document.getElementsByClassName("librarycheck");
        for (var i = 0; i < list.length; i++) {
                list[i].checked = false;
        }
    if (document.getElementById('multiCheck').checked) {
	var list = document.getElementsByClassName("multiplereq");
	for (var i = 0; i < list.length; i++) {
		list[i].style.display = 'block';
	}
	var list = document.getElementsByClassName("singlereq");
        for (var i = 0; i < list.length; i++) {
                list[i].style.display = 'none';
        }
    } else {
        var list = document.getElementsByClassName("multiplereq");
        for (var i = 0; i < list.length; i++) {
                list[i].style.display = 'none';
        }
        var list = document.getElementsByClassName("singlereq");
        for (var i = 0; i < list.length; i++) {
                list[i].style.display = 'block';
        }
}
}

function failcheck() {
var checkBoxes = document.getElementsByClassName( 'librarycheck' );
var isChecked = false;
    for (var i = 0; i < checkBoxes.length; i++) {
        if ( checkBoxes[i].checked ) {
            isChecked = true;
	    break;
        };
    };
    if ( !isChecked ) {
            alert( 'Please select at least one library!' );
        }   
}
