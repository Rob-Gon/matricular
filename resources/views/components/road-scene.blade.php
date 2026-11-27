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
    {{--
        Centre dashes rushing towards the player. Projected from the ground (y = 200 + 800 / distance) and
        spaced geometrically, so scaling the group from the vanishing point by that ratio (1.35) maps each
        dash onto the next one and the loop is seamless (.road-dashes in app.css).
    --}}
    <g class="road-dashes" fill="#E9A92B">
        <polygon points="1257.4,1490.3 1302.6,1490.3 1299.1,1293.5 1260.9,1293.5" />
        <polygon points="1263.3,1155.8 1296.7,1155.8 1294.2,1010.0 1265.8,1010.0" />
        <polygon points="1267.6,908.0 1292.4,908.0 1290.5,800.0 1269.5,800.0" />
        <polygon points="1270.8,724.4 1289.2,724.4 1287.8,644.4 1272.2,644.4" />
        <polygon points="1273.2,588.5 1286.8,588.5 1285.8,529.2 1274.2,529.2" />
        <polygon points="1275.0,487.8 1285.0,487.8 1284.3,443.9 1275.7,443.9" />
        <polygon points="1276.3,413.2 1283.7,413.2 1283.2,380.6 1276.8,380.6" />
        <polygon points="1277.2,357.9 1282.8,357.9 1282.3,333.8 1277.7,333.8" />
        <polygon points="1278.0,317.0 1282.0,317.0 1281.7,299.1 1278.3,299.1" />
        <polygon points="1278.5,286.6 1281.5,286.6 1281.3,273.4 1278.7,273.4" />
        <polygon points="1278.9,264.2 1281.1,264.2 1281.0,254.4 1279.0,254.4" />
        <polygon points="1279.2,247.5 1280.8,247.5 1280.7,240.3 1279.3,240.3" />
        <polygon points="1279.4,235.2 1280.6,235.2 1280.5,229.8 1279.5,229.8" />
        <polygon points="1279.5,226.1 1280.5,226.1 1280.4,222.1 1279.6,222.1" />
        <polygon points="1279.7,219.3 1280.3,219.3 1280.3,216.4 1279.7,216.4" />
        <polygon points="1279.7,214.3 1280.3,214.3 1280.2,212.1 1279.8,212.1" />
        <polygon points="1279.8,210.6 1280.2,210.6 1280.2,209.0 1279.8,209.0" />
        <polygon points="1279.9,207.9 1280.1,207.9 1280.1,206.7 1279.9,206.7" />
    </g>
    {{-- Drawn last so the road disappears behind the hills. --}}
    <path d="M0 196 Q140 170 280 188 T560 196 Q720 150 890 186 T1220 182 Q1280 176 1340 182 T1670 172 T2000 190 Q2140 166 2280 186 T2560 192 L2560 212 L0 212 Z" fill="#1E4A44" stroke="#000000" stroke-width="6" stroke-linejoin="round" />
    <rect width="2560" height="1000" filter="url(#road-scene-grain)" opacity="0.18" style="mix-blend-mode: multiply" />
</svg>
