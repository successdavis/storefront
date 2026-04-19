<x-mail::message>
# Hello {{ $customer->name }},

{!! nl2br(e($messageBody)) !!}

@if($senderName)
Thanks,<br>
{{ $senderName }}
@else
Thanks,<br>
{{ config('app.name') }}
@endif
</x-mail::message>
