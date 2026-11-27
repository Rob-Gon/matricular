{{--
    Desktop game backdrop: a road running from the bottom of the screen to the horizon, so the car
    (seen from behind) drives on it. The scene is 2560 wide so up to that aspect ratio it scales with
    the height: the horizon stays above the car on wide screens and only the sides are cropped.
--}}
<svg {{ $attributes }} aria-hidden="true" viewBox="0 0 2560 1000" preserveAspectRatio="xMidYMin slice">
    <defs>
        <filter id="road-scene-grain">
            <feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="3" stitchTiles="stitch" />
            <feColorMatrix type="saturate" values="0" />
        </filter>
    </defs>
    <rect width="2560" height="1000" fill="#265E57" />
    <rect width="2560" height="200" fill="#E7D7B0" />
    {{-- Low sun half hidden by the hills, away from the road's vanishing point. --}}
    <circle cx="990" cy="152" r="56" fill="#E9A92B" stroke="#000000" stroke-width="6" />
    <polygon points="630,1004 1930,1004 1288,200 1272,200" fill="#3A3A36" stroke="#000000" stroke-width="8" stroke-linejoin="round" />
    <polygon points="678,1004 702,1004 1276.5,203 1275.5,203" fill="#E7D7B0" />
    <polygon points="1858,1004 1882,1004 1284.5,203 1283.5,203" fill="#E7D7B0" />
    {{-- Centre dashes shrink and bunch up towards the horizon (y = 200 + 800 / distance). --}}
    <g fill="#E9A92B">
        <polygon points="1266,1004 1294,1004 1289.3,733.3 1270.7,733.3" />
        <polygon points="1273,600 1287,600 1285.6,520 1274.4,520" />
        <polygon points="1275.3,466.7 1284.7,466.7 1284,428.6 1276,428.6" />
        <polygon points="1276.5,400 1283.5,400 1283.1,377.8 1276.9,377.8" />
        <polygon points="1277.2,360 1282.8,360 1282.5,345.5 1277.5,345.5" />
        <polygon points="1277.7,333.3 1282.3,333.3 1282.2,323.1 1277.8,323.1" />
        <polygon points="1278,314.3 1282,314.3 1281.9,306.7 1278.1,306.7" />
    </g>
    {{-- Drawn last so the road disappears behind the hills. --}}
    <path d="M0 196 Q140 170 280 188 T560 196 Q720 150 890 186 T1220 182 Q1280 176 1340 182 T1670 172 T2000 190 Q2140 166 2280 186 T2560 192 L2560 212 L0 212 Z" fill="#1E4A44" stroke="#000000" stroke-width="6" stroke-linejoin="round" />
    <rect width="2560" height="1000" filter="url(#road-scene-grain)" opacity="0.18" style="mix-blend-mode: multiply" />
</svg>
