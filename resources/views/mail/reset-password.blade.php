<x-mail::message>
Olá {{ $name }},

Recebemos um pedido para redefinir a tua palavra-passe na Mticket.

<x-mail::button :url="$url">
Redefinir palavra-passe
</x-mail::button>

Este link é válido por 60 minutos. Se não pediste esta alteração, podes ignorar este email.

Obrigado,<br>
{{ config('app.name') }}
</x-mail::message>
