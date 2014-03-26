{$question.question_number}. {$question.text}

{let result=fetch('survey', 'text_entry_result_item', hash( 'question', $question, 'metadata', $metadata, 'result_id', $result_id ))}
<a href="{$result|ezurl(no,full)}" target="file">{$result|ezurl(no,full)}</a>
{/let}