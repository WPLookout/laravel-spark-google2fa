<?php

namespace Eusebiu\LaravelSparkGoogle2FA;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Spark\Contracts\Interactions\Settings\Security\EnableTwoFactorAuth;
use Laravel\Spark\Http\Controllers\Settings\Security\TwoFactorAuthController as Controller;
use Laravel\Spark\Spark;
use PragmaRX\Google2FAQRCode\Google2FA;
use PragmaRX\Google2FAQRCode\QRCode\Bacon;

class TwoFactorAuthController extends Controller
{
    /**
     * @var \PragmaRX\Google2FAQRCode\Google2FA
     */
    protected $g2fa;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
	public function __construct()
	{
		$this->g2fa = new Google2FA(
			new Bacon(
				new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
			)
		);
	}

    /**
     * Generate a QR code.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function generate(Request $request)
    {
        $secret = $this->g2fa->generateSecretKey();

        $request->session()->put('spark:twofactor:secret', $secret);

        return [
            'secret' => $secret,
            'qrcode' => $this->qrCode($request->user()->email, $secret),
        ];
    }

    /**
     * Enable Google two-factor authentication.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function enable2fa(Request $request)
    {
        $this->validate($request, ['code' => 'required|min:6']);

        $secret = $request->session()->get('spark:twofactor:secret');

        if (! $this->g2fa->verifyKey($secret, $request->code)) {
            throw ValidationException::withMessages([
               'code' => [__('The code is invalid.')],
           ]);
        }

        Spark::interact(EnableTwoFactorAuth::class, [$request->user()]);

        return $this->storeTwoFactorInformation($request);
    }

    /**
     * Get the qr code image url.
     *
     * @param  string $email
     * @param  string $secret
     * @return string
     */
    protected function qrCode($email, $secret)
    {
        $company = Spark::$details['2fa_name'] ??
                    Spark::$details['vendor'] ??
                    url()->to('/');

        return $this->g2fa->getQRCodeInline(urlencode($company), $email, $secret, 260);
    }
}
