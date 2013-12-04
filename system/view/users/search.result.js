{% if nicks %}
  $('#nick_list_placeholder').empty();
  {% for nick in nicks %}
  $('#nick_list_placeholder').append('<div><a href="private.phtml?user_id={{ nick.user_id }}"><b>{{ nick.nick }}</b></a></div>');
  {% endfor %}
{% else %}
$('#nick_list_placeholder').html('Ничего не найдено');
{% endif %}