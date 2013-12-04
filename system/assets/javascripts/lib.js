// Устанавливаем дату истечения cookies на год.
var largeExpDate = new Date ();
largeExpDate.setTime(largeExpDate.getTime() + (365 * 24 * 3600 * 1000));

function getCookie(name) {
  var prefix = name + '=';
  var start = document.cookie.indexOf(prefix);
  if (start == -1) {
    return '';
  }
  start += prefix.length;
  var end = document.cookie.indexOf(';', start);
  if (end == -1) {
    end = document.cookie.length;
  }
  return unescape(document.cookie.substring(start, end));
}

function toggleMsgAjax(message_id, deletion) {
  $.ajax({
    type: "POST",
    url: "messages.phtml",
    data: {id: message_id, deletion: deletion, action: 'toggle'},
    dataType: 'script'
  });
}

function dontTrace(id, dont_trace) {
  $.ajax({
    type: "POST",
    url: "discussions.phtml",
    data: {discussion: id, dont_trace: dont_trace, action: 'trace'},
    dataType: 'script'
  });
}

function vote(discussion_id, option_id) {
  $.ajax({
    type: "POST",
    url: "votes.phtml",
    data: {discussion_id: discussion_id, option_id: option_id},
    dataType: 'script'
  });
}

function voteAddOption(discussion_id, opt) {
  if (!opt) return;
  $.ajax({
    type: "POST",
    url: "votes.phtml",
    data: {discussion_id: discussion_id, option: opt, action: 'addOption'},
    dataType: 'script'
  });
}

function voteDeleteOption(discussion_id, option_id) {
  if (!confirm('Точно удалить?')) {
    return false;
  }
  $.ajax({
    type: "POST",
    url: "votes.phtml",
    data: {discussion_id: discussion_id, option_id: option_id, action: 'destroy'},
    dataType: 'script'
  });
  return false;
}

function loadMessageForm(discussion_id) {
  $('#add_link_' + discussion_id).hide();
  $('#add_form_placeholder_' + discussion_id).html(
    '<div class="answer form">' +
    '<TEXTAREA id="add_message_' + discussion_id + '" style="width: 100%; height: 6em;"></TEXTAREA>' +
    '<div class="actions">' +
    '<a class="button" href="javascript:sendMessage(' + discussion_id + ')">Отправить</a>' +
    '<a class="button cancel" href="javascript:cancelMessage(' + discussion_id + ')">Отменить</a>' +
    '</div></div>');
  $('#add_form_placeholder_' + discussion_id + ' textarea').focus();
}

function sendMessage(discussion_id) {
  var text = document.getElementById('add_message_' + discussion_id).value;
  if (!text) {
    alert('А смысл? Где сообщение-то?');
    return;
  }
  $.ajax({
    type: "POST",
    url: "messages.phtml",
    data: {discussion_id: discussion_id, text: text, action: 'addMessage'},
    dataType: 'script'
  });
}

function cancelMessage(discussion_id) {
  $('#add_form_placeholder_' + discussion_id).empty();
  $('#add_link_' + discussion_id).show();
}

function editInline(message_id) {
  $('#admin_message_placeholder_' + message_id).load('messages.phtml',
    {action: 'edit', message_id: message_id}, function() { document.getElementById('message_' + message_id + '_text').focus(); });
}

function cancelEdit(message_id) {
  $('#admin_message_placeholder_' + message_id).empty();
}

function updateMessage(message_id) {
  var text = document.getElementById('message_' + message_id + '_text').value;
  var comment = document.getElementById('message_' + message_id + '_comment').value;
  var move_to_discussion_id = document.getElementsByName('discussion_id_for_message_' + message_id)[0].value;
  $.ajax({
    type: "POST",
    url: "messages.phtml",
    data: { action: 'update', message_id: message_id, move_to_discussion_id: move_to_discussion_id, text: text, comment: comment },
    dataType: 'script'
  });
}

function setTill(frm) {
  currentDate = new Date();
  tillDate = new Date();
  ind = frm.termSelect.selectedIndex;
  if (ind == 0) {;
    return;
  }
  if (ind == 1) { // year;
    tillDate.setFullYear(currentDate.getFullYear() + 1);
  } else if (ind == 2) { // halfyear
    tillDate.setMonth(currentDate.getMonth() + 6);
  } else if (ind == 3) { // month
    tillDate.setMonth(currentDate.getMonth() + 1);
  } else if (ind == 4) { // week
    tillDate.setDate(currentDate.getDate() + 7);
  } else if (ind == 5) { // day
    tillDate.setDate(currentDate.getDate() + 1);
  }
  frm.till_day.selectedIndex = tillDate.getDate();
  frm.till_month.selectedIndex = tillDate.getMonth() + 1;
  frm.till_year.selectedIndex = tillDate.getFullYear() - 2005;
}

function blockUser(message_id) {
  $('#admin_message_placeholder_' + message_id).load('blocks.phtml',
    {action: 'new', message_id: message_id});
}

function submitBlock(message_id) {
  var rule_id = document.getElementsByName('rule_id')[0].value;
  var till_day = document.getElementsByName('till_day')[0].value;
  var till_month = document.getElementsByName('till_month')[0].value;
  var till_year = document.getElementsByName('till_year')[0].value;
  var comment = document.getElementById('block_message_' + message_id + '_comment').value;
  $.ajax({
    type: "POST",
    url: "blocks.phtml",
    data: { action: 'create', message_id: message_id,
            rule_id: rule_id, comment: comment, till_day: till_day, till_month: till_month, till_year: till_year },
    dataType: 'script'
  });
}

function warnUser(message_id) {
  $('#admin_message_placeholder_' + message_id).load('warns.phtml',
    {action: 'new', message_id: message_id});
}

function submitWarn(message_id) {
  var rule_id = document.getElementsByName('rule_id')[0].value;
  var comment = document.getElementById('warn_message_' + message_id + '_comment').value;
  var del_message = document.getElementById('del_message_' + message_id).checked ? 1 : 0;
  var del_discussion_element = document.getElementById('del_discussion_message_' + message_id);
  var del_discussion = del_discussion_element ? (del_discussion_element.checked ? 1 : 0) : 0;
  $.ajax({
    type: "POST",
    url: "warns.phtml",
    data: { action: 'create', message_id: message_id,
            rule_id: rule_id, comment: comment, del_message: del_message, del_discussion: del_discussion },
    dataType: 'script'
  });
}

function findNick(discussion_id) {
  var nick = document.getElementById('find_nick').value;
  if (!nick) return;
  $.ajax({
    type: "POST",
    url: "users.phtml",
    data: {action: 'find', nick: nick},
    dataType: 'script'
  });
}

function loadPrev(discussion_id, last_time) {
  $.ajax({
    type: "POST",
    url: "messages.phtml",
    data: {discussion_id: discussion_id, action: 'loadPrev', last_time: last_time},
    dataType: 'script'
  });
}

$(document).ready(function(){
  $("span.dropdown_menu_container").hover(
    function() {
      var position = $(this).offset();
      var menu = $(this).find("ul.dropdown_menu");
      menu.css({left: position.left, top: position.top + 14});
      menu.stop(true, true);
      menu.delay(200).slideDown();
    },
    function() {
      var menu = $(this).find("ul.dropdown_menu");
      menu.slideUp("fast");
    }
  );
});
