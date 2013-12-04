$('#prev_{{ discussion_id }}').empty();
$('<div class="just_added" style="display: none;">{% filter escape('js') %}{% for message in messages %}{% include 'discussions/message.html' with { msg: message } %}{% endfor %}{% endfilter %}</div>').insertAfter('#prev_{{ discussion_id }}').fadeIn();
{% if messages %}
$('#prev_{{ discussion_id }}').html('{% filter escape('js') %}{% include 'message/load.prev.link.html' %}{% endfilter %}');
{% endif %}