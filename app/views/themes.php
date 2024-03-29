<div class="page-content margin-fix">
    <h2>Themes</h2>

    <p class="is-font-medium">
        Download additional themes to customize your workspace to fit your personal need.<br/><br/>
    </p>

   <div class="themes">
        @foreach ($themes as $theme)
            <a href="{{ asset('img/themes/' . $theme->get('preview')) }}">
                <div class="theme" style="background-image: url('{{ asset('img/themes/' . $theme->get('preview')) }}');">
                    <div class="theme-info">
                        <div class="theme-info-title">{{ $theme->get('name') }}</div>

                        <div class="theme-info-download is-clickable" onclick="window.open('{{ asset('downloads/' . $theme->get('download')) }}'); return false;">
                            <i class="fas fa-download fa-lg"></i>
                        </div>
                    </div>
                </div>
            </a>
        @endforeach
   </div>

   <p class="is-font-medium margin-top-gap">Made a cool theme that you want to share?</p>

    <p>
        <a class="button is-link button-stretched" href="mailto:{{ env('APP_CONTACT') }}">Contact Us</a>
    </p>
</div>