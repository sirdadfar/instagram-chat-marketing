@php($isOut=$message->direction==='outbound')
<div class="message-line {{ $isOut?'outbound':'inbound' }}" data-message-line>
<div class="message-bubble {{ $isOut?'outgoing':'incoming' }}" data-message-id="{{ $message->external_message_id }}">
@if($message->text)<div class="bubble-text">{!! nl2br(e($message->text)) !!}</div>@endif
@if($message->attachments)
<div class="message-attachments">@foreach($message->attachments as $att)<div class="message-attachment">@if(($att['type']??'')==='image')<img src="{{ $att['url']??'' }}" alt="رسانه">@elseif(($att['type']??'')==='video')<video controls preload="metadata" src="{{ $att['url']??'' }}"></video>@elseif(($att['type']??'')==='audio')<audio controls src="{{ $att['url']??'' }}"></audio>@else<a target="_blank" rel="noreferrer" href="{{ $att['url']??'#' }}">فایل پیوست ↗</a>@endif</div>@endforeach</div>
@endif
@if($isOut && $message->external_message_id)<div class="bubble-tools"><button type="button" class="reaction-button" data-react-message data-url="{{ route('inbox.message.react',[$conversation,$message->external_message_id]) }}" data-emoji="❤️">♡</button></div>@endif
<div class="bubble-time">{{ $message->sent_at?->format('H:i') }}</div>
</div></div>
