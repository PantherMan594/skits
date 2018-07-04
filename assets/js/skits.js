var character = 'all';
var showAround = true;
var showAll = true;
var showLine = true;
var showCue = true;
var showOtherLines = true;
var showStage = true;

function update(keepUrl) {
    $('#char').val(character);
    $('#showaround').prop('checked', showAround);
    $('#showall').prop('checked', showAll);
    $('#showline').prop('checked', showLine);
    $('#showcue').prop('checked', showCue);
    $('#showotherlines').prop('checked', showOtherLines);
    $('#showstage').prop('checked', showStage);
    var lines = $('#skitlines').children('.line');
    for (var i = 0; i < lines.length; i++) {
        var line = $(lines[i]);
        var prevLine = $(lines[i - 1]);
        var nextLine = $(lines[i + 1]);
        line.removeClass('highlight');
        if (line.hasClass('stage')) {
            if (showStage) line.show();
            else line.hide();
        } else if (!line.hasClass('scene')) {
            if (line.hasClass(character)) {
                if (showCue) {
                    line.show();
                    line.addClass('highlight');
                    if (!showLine) {
                        line.addClass('hideLine');
                    } else {
                        line.removeClass('hideLine');
                    }
                } else {
                    line.hide();
                }
            } else if (showAll || (showAround && ((prevLine && prevLine.hasClass(character)) || (nextLine && nextLine.hasClass(character))))) {
                line.show();
                if (!showOtherLines) {
                    line.addClass('hideLine');
                } else {
                    line.removeClass('hideLine');
                }
            } else {
                line.hide();
            }
        }
    }

    if (!keepUrl)
        history.pushState('', '', window.location.pathname
            + '?id=' + id + '&c='+ character
            + (showCue ? '' : '&cu=0') + (showOtherLines ? '' : '&ot=0')
            + (showAll ? '' : '&al=0') + (showAround ? '' : '&ar=0')
            + (showLine ? '' : '&li=0') + (showStage ? '' : '&st=0'));
}

var urlParams;
(window.onpopstate = function () {
    var match,
        pl     = /\+/g,  // Regex for replacing addition symbol with a space
        search = /([^&=]+)=?([^&]*)/g,
        decode = function (s) { return decodeURIComponent(s.replace(pl, " ")); },
        query  = window.location.search.substring(1);

    urlParams = {};
    while (match = search.exec(query))
        urlParams[decode(match[1])] = decode(match[2]);
    character = urlParams['c'] ? urlParams['c'] : 'all';
    showAround = urlParams['ar'] ? false : true;
    showAll = urlParams['al'] ? false : true;
    showLine = urlParams['li'] ? false : true;
    showCue = urlParams['cu'] ? false : true;
    showOtherLines = urlParams['ot'] ? false : true;
    showStage = urlParams['st'] ? false : true;
    update(true);
})();

$(document).ready(function () {    
    $('#controlbox').append('<input type="checkbox" id="showaround" value="around" checked/> <label for="showaround">Show lines around character (to practice getting in/out of lines)</label><br />'
        + '<input type="checkbox" id="showall" value="all" checked/> <label for="showall">Show all other lines</label><br />'
        + '<input type="checkbox" id="showline" value="line" checked/> <label for="showline">Show character line (uncheck to memorize lines, though you can still highlight them to see the text)</label><br />'
        + '<input type="checkbox" id="showcue" value="cue" checked/> <label for="showcue">Show character line cues (completely hide the lines, to memorize getting in/out of lines)</label><br />'
        + '<input type="checkbox" id="showotherlines" value="other" checked/> <label for="showotherlines">Show other lines (uncheck if you don\'t care what other characters are saying, but just need their cues)</label><br />'
        + '<input type="checkbox" id="showstage" value="stage" checked/> <label for="showstage">Show stage directions</label>');
    
    $('#char').change(function () {
        character = $('#char option:selected').val();
        update();
    });

    $('#controlbox input').change(function () {
        switch (this.value) {
            case 'around':
                showAround = this.checked;
                break;
            case 'all':
                showAll = this.checked;
                break;
            case 'line':
                showLine = this.checked;
                break;
            case 'cue':
                showCue = this.checked;
                break;
            case 'other':
                showOtherLines = this.checked;
                break;
            case 'stage':
                showStage = this.checked;
                break;
        }
        update();
    });

    // Add names
    var characters = $('#char').children();
    for (var i = characters.length - 1; i > 0; i--) {
        var matches = $('.' + characters[i].value);
        for (var j = 0; j < matches.length; j++) {
            if ($(matches[j]).children('span.name').length !== 0) {
                var name = $(matches[j]).children('span.name')[0];
                name.innerHTML = characters[i].innerHTML + ' and ' + name.innerHTML;
            } else {
                $(matches[j]).prepend('<span class="name">' + characters[i].innerHTML + ': </span>');
            }
        }
    }

    update(true);
});
