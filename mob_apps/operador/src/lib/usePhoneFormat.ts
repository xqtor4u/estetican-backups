import { useEffect, useState } from 'react';

export interface PhoneFormat {
  allowCountryCode: boolean;
  minDigits: number;
  maxDigits: number;
}

const DEFAULT_FORMAT: PhoneFormat = { allowCountryCode: false, minDigits: 10, maxDigits: 10 };

/* México/Norteamérica exige 10 dígitos exactos por default — activable desde Configuración →
   Clientes ("Permitir código de país en teléfonos") para zonas fronterizas o clientes de otro
   país, que amplía el rango según el estándar internacional E.164 (hasta 15 dígitos en total). */
export function usePhoneFormat(): PhoneFormat {
  const [format, setFormat] = useState<PhoneFormat>(DEFAULT_FORMAT);

  useEffect(() => {
    fetch('/api/settings/phone-format')
      .then(r => r.json())
      .then(data => {
        if (typeof data?.min_digits === 'number' && typeof data?.max_digits === 'number') {
          setFormat({
            allowCountryCode: Boolean(data.allow_country_code),
            minDigits: data.min_digits,
            maxDigits: data.max_digits,
          });
        }
      })
      .catch(() => {});
  }, []);

  return format;
}

export function phoneDigitsHint({ minDigits, maxDigits }: PhoneFormat): string {
  return minDigits === maxDigits ? `${minDigits} dígitos` : `entre ${minDigits} y ${maxDigits} dígitos`;
}
