$('#add_form_placeholder_{{ discussion_id }}').empty();
$('<div class="just_added" style="display: none;">{% filter escape('js') %}{% for message in messages %}{% include 'discussions/message.html' with { msg: message } %}{% endfor %}{% endfilter %}</div>').insertBefore('#new_message_placeholder_{{ discussion_id }}').fadeIn();
$('#add_link_{{ discussion_id }}').show();
