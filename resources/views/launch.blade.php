{{--
    The launch animation: NativePHP opens the app on this page (nativephp.start_url = /launch).
    The Zentro logo video — a 3.5 s cut of "Logo Video.mp4" — plays once, then the app carries on
    to the dashboard (which sends a locked app to the PIN screen, a first launch to the welcome).
    A tap skips it; if the video cannot play, the app moves on regardless.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Zentro Invoice</title>
    <style>
        html, body {
            margin: 0;
            height: 100%;
            overflow: hidden;
            /* The video's own navy, darkening to its vignette at the edges, so its square edge
               never shows against the screen. */
            background: radial-gradient(circle at 50% 50%, #0d182f 0%, #08101f 45%, #03070f 100%);
        }
        .stage {
            position: fixed;
            inset: 0;
            display: grid;
            place-items: center;
            transition: opacity .35s ease;
        }
        .stage.leaving { opacity: 0; }
        video {
            width: min(92vw, 92vh, 540px);
            height: auto;
            -webkit-mask-image: radial-gradient(circle at 50% 50%, #000 52%, transparent 71%);
            mask-image: radial-gradient(circle at 50% 50%, #000 52%, transparent 71%);
        }
    </style>
</head>
<body>
    <div class="stage" id="stage">
        <video id="intro" src="{{ asset('assets/app/launch/zentro-launch.mp4') }}" autoplay muted playsinline preload="auto"></video>
    </div>
    <script>
        (function () {
            var done = false;
            var target = @js(route('dashboard'));
            function go() {
                if (done) { return; }
                done = true;
                document.getElementById('stage').classList.add('leaving');
                setTimeout(function () { location.replace(target); }, 300);
            }
            var video = document.getElementById('intro');
            video.addEventListener('ended', function () { setTimeout(go, 250); });
            video.addEventListener('error', go);
            document.addEventListener('click', go);
            // Whatever happens to the video, never hold the app here.
            setTimeout(go, 6000);
            var played = video.play();
            if (played && played.catch) { played.catch(go); }
        })();
    </script>
</body>
</html>
