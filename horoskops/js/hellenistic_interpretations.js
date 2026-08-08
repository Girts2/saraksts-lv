/**
 * hellenistic_interpretations.js - "Sistēmas X" Hellēnistiskā bloka dati
 */

export const HELLENISTIC_SECT = {
    "Diena": {
        name: "Dienas Sekta",
        desc: "Subjekts ir racionāls, sociāli aktīvs un orientēts uz objektīvo realitāti. Autoritātes un likums ir svarīgi.",
        benefic: "Jupiters (Lielākais atbalsts)",
        malefic: "Marss (Lielākais risks/izaicinājums)"
    },
    "Nakts": {
        name: "Nakts Sekta",
        desc: "Subjekts ir intuitīvs, emocionāli dziļš un orientēts uz subjektīvo pieredzi. Darbojas labāk neformālā vidē.",
        benefic: "Venēra (Lielākais atbalsts)",
        malefic: "Saturns (Lielākais risks/izaicinājums)"
    }
};

export const HELLENISTIC_HUMORS = {
    "Choleric": "Holeriķis (Uguns: Karsts un sauss). Strauja, agresīva reakcija. Augsta griba, zema pacietība.",
    "Sanguine": "Sangviniķis (Gaiss: Karsts un mitrs). Ātra, sociāla reakcija. Augsta adaptācija, zems dziļums.",
    "Melancholic": "Melanholiķis (Zeme: Auksts un sauss). Lēna, analītiska reakcija. Augsta izturība, tieksme uz izolāciju.",
    "Phlegmatic": "Flegmatiķis (Ūdens: Auksts un mitrs). Lēna, emocionāla reakcija. Augsta empātija, tieksme uz pasivitāti.",
    "Choleric-Melancholic": "Holeriski-Melanholiskais (Hibrīds). Kombinē agresīvu uzbrukumu ar dziļu strukturālu analīzi.",
    "Choleric-Phlegmatic": "Holeriski-Flegmatiskais (Hibrīds). Ātri aizsvilstas, bet ātri nodziest un pielāgojas.",
    "Choleric-Sanguine": "Holeriski-Sangviniskais (Hibrīds). Līderis un komunikators, strauji izplešas.",
    "Sanguine-Melancholic": "Sangviniski-Melanholiskais (Hibrīds). Sociālā maska pretstatā dziļai iekšējai izolācijai.",
    "Sanguine-Phlegmatic": "Sangviniski-Flegmatiskais (Hibrīds). Maksimāla adaptācija kolektīva noskaņojumam, plūsmas uzturētājs.",
    "Melancholic-Phlegmatic": "Melanholiski-Flegmatiskais (Hibrīds). Ārkārtīgi lēna dinamika. Iekšupvērsta izturība bez atklāta uzbrukuma."
};

export const LOT_INTERPRETATIONS = {
    "Spirit": "Gara daļa (Lot of Spirit): Subjekta intelektuālā griba un apzinātie mērķi. Tas, ko viņš izvēlas darīt.",
    "Fortune": "Likteņa daļa (Lot of Fortune): Subjekta fiziskā labklājība, ķermenis un nejaušības, kas ar viņu notiek."
};

/**
 * buildHellenisticProfile - Sintēzes funkcija 12 punktiem
 */
export function buildHellenisticProfile(data) {
    const { sect, humor, helmsman, spiritLot, fortuneLot } = data;

    return {
        "1. Pamatraksturs (Ascendents)": `Dzīves stūrmanis: ${helmsman.planet} (${helmsman.condition}). Fokuss: ${helmsman.house}. māja.`,
        "2. Bāzes temperaments": HELLENISTIC_HUMORS[humor] || "Hibrīda Temperaments",
        "3. Emocionālais fons": `${HELLENISTIC_SECT[sect].name}: ${HELLENISTIC_SECT[sect].desc}`,
        "4. Iekšējā motivācija": `${LOT_INTERPRETATIONS.Spirit} Pozīcija: ${spiritLot.sign}.`,
        "5. Adaptācija realitātei": `${LOT_INTERPRETATIONS.Fortune} Pozīcija: ${fortuneLot.sign}.`,
        "7. Atbalsta resursi": `Subjekta dabiski labvēlīgais faktors: ${HELLENISTIC_SECT[sect].benefic}.`,
        "8. Iekšējie šķēršļi": `Kritiskais destruktīvais faktors: ${HELLENISTIC_SECT[sect].malefic}.`,
        "11. Dzīves vadošais spēks": `Oikodespotes: ${data.oikodespotes}. Noteicošā dzīves tēma un 'kapteinis'.`
    };
}
