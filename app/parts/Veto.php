<?php
/**
 * Immutable value returned by a listener to stop triggerWithVeto().
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   2.0 FREE
 * @license   MIT License
 * @date      2014 - 2026
 *
 * License Notice:
 * You are permitted to use, modify, and distribute this code under the
 * following condition: You must retain this header in all copies or
 * substantial portions of the code, including the author and company information.
 */

namespace Dotsystems\App\Parts;

final class Veto
{
    private $code;
    private $message;
    private $details;

    /**
     * Vytvori nemenny dovod na zastavenie veto udalosti.
     *
     * @param string $code Stabilny lowercase kod pre rozhodovanie volajuceho.
     * @param string $message Interny popis dovodu, nie automaticka odpoved pre klienta.
     * @param array<string, mixed> $details Bezpecne doplnujuce data bez hesiel, tokenov alebo request body.
     * @throws \InvalidArgumentException Ked kod nema stabilny podporovany tvar.
     */
    public function __construct(string $code, string $message = '', array $details = [])
    {
        $code = trim($code);
        // Why: stabilny kod musi byt bez medzier a vhodny na porovnanie bez lokalizovaneho textu.
        if (!preg_match('/^[a-z][a-z0-9._-]{0,63}$/', $code)) {
            throw new \InvalidArgumentException('Veto code must be a lowercase identifier up to 64 characters.');
        }

        $this->code = $code;
        $this->message = $message;
        $this->details = $details;
    }

    /**
     * Vrati stabilny strojovy kod veta.
     *
     * @return string Lowercase identifikator dovodu.
     */
    public function code(): string
    {
        return $this->code;
    }

    /**
     * Vrati interny text dovodu bez automatickeho zobrazenia klientovi.
     *
     * @return string Popis urceny volajucemu alebo logu.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Vrati doplnujuce bezpecne data veta.
     *
     * @return array<string, mixed> Kopia detailov ulozenych pri vytvoreni.
     */
    public function details(): array
    {
        return $this->details;
    }
}
