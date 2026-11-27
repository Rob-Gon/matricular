@props(['letters', 'digits' => null, 'drop' => false])

<div {{ $attributes->class('car') }}>
    <svg class="animate-ink-boil" viewBox="0 0 800 520" aria-hidden="true">
        <ellipse cx="400" cy="496" rx="340" ry="20" fill="#000000" opacity="0.22" />
        {{-- Seen from behind, the tyres show their tread face: tall rounded slabs. --}}
        <rect x="132" y="372" width="86" height="130" rx="22" fill="#000000" />
        <rect x="582" y="372" width="86" height="130" rx="22" fill="#000000" />
        <path d="M162 456 V494 M188 456 V494 M612 456 V494 M638 456 V494" stroke="#3A3A36" stroke-width="5" stroke-linecap="round" />
        <path d="M228 214 L248 94 Q251 74 272 74 L528 74 Q549 74 552 94 L572 214 Z" fill="#A82C18" stroke="#000000" stroke-width="8" stroke-linejoin="round" />
        <rect x="298" y="98" width="204" height="80" rx="12" fill="#9FC4BE" stroke="#000000" stroke-width="7" />
        <path d="M322 164 L350 112 M346 166 L364 134" fill="none" stroke="#FBF8EF" stroke-width="8" stroke-linecap="round" />
        <path d="M110 420 C94 300 122 232 182 206 L618 206 C678 232 706 300 690 420 Z" fill="#C8361F" stroke="#000000" stroke-width="8" stroke-linejoin="round" />
        <path d="M150 240 Q170 222 204 218" fill="none" stroke="#F07A5C" stroke-width="8" stroke-linecap="round" />
        <circle cx="400" cy="250" r="58" fill="#000000" />
        <circle cx="400" cy="250" r="32" fill="#E9A92B" stroke="#000000" stroke-width="6" />
        <circle cx="400" cy="250" r="9" fill="#000000" />
        <circle cx="172" cy="296" r="25" fill="#F05A3C" stroke="#000000" stroke-width="7" />
        <circle cx="164" cy="288" r="7" fill="#FBF8EF" />
        <circle cx="628" cy="296" r="25" fill="#F05A3C" stroke="#000000" stroke-width="7" />
        <circle cx="620" cy="288" r="7" fill="#FBF8EF" />
        <path d="M76 448 Q78 350 176 342 Q248 346 262 448 Z" fill="#C8361F" stroke="#000000" stroke-width="8" stroke-linejoin="round" />
        <path d="M538 448 Q552 346 624 342 Q722 350 724 448 Z" fill="#C8361F" stroke="#000000" stroke-width="8" stroke-linejoin="round" />
        <rect x="58" y="412" width="684" height="38" rx="19" fill="#D9D6C8" stroke="#000000" stroke-width="8" />
        <path d="M92 424 L330 424" fill="none" stroke="#FBF8EF" stroke-width="6" stroke-linecap="round" />
    </svg>
    <div class="car-plate">
        <x-plate :letters="$letters" :digits="$digits" :drop="$drop" />
    </div>
</div>
