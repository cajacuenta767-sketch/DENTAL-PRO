<?php

namespace App\Services\Pasarela;

use RuntimeException;

/** El webhook no trae una firma válida o su cuerpo no se puede interpretar. */
class WebhookInvalido extends RuntimeException {}
