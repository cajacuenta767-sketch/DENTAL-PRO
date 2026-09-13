<footer class="footer footer-transparent d-print-none">
    <div class="container-xl">
        <div class="row text-center align-items-center flex-row-reverse">
            <div class="col-lg-auto ms-lg-auto">
                <ul class="list-inline list-inline-dots mb-0">
                    <li class="list-inline-item"><a href="{{ route('publico.inicio') }}" class="link-secondary">Sitio público</a></li>
                    <li class="list-inline-item"><span class="text-secondary">v{{ config('odontosuite.version') }}</span></li>
                </ul>
            </div>
            <div class="col-12 col-lg-auto mt-3 mt-lg-0">
                <ul class="list-inline list-inline-dots mb-0">
                    <li class="list-inline-item">
                        Copyright &copy; {{ date('Y') }}
                        <span class="link-secondary">{{ $ajustes->nombre ?? 'OdontoSuite' }}</span>.
                        Todos los derechos reservados.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</footer>
