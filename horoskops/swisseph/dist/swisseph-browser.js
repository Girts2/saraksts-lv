var __defProp = Object.defineProperty;
var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
var __getOwnPropNames = Object.getOwnPropertyNames;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __name = (target, value) => __defProp(target, "name", { value, configurable: true });
var __esm = (fn, res) => function __init() {
  return fn && (res = (0, fn[__getOwnPropNames(fn)[0]])(fn = 0)), res;
};
var __copyProps = (to, from, except, desc) => {
  if (from && typeof from === "object" || typeof from === "function") {
    for (let key of __getOwnPropNames(from))
      if (!__hasOwnProp.call(to, key) && key !== except)
        __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
  }
  return to;
};
var __toCommonJS = (mod) => __copyProps(__defProp({}, "__esModule", { value: true }), mod);

// ../core/dist/enums.js
var enums_exports = {};
var CalendarType, Planet, LunarPoint, Asteroid, FictitiousPlanet, HouseSystem, HousePoint, CalculationFlag, EclipseType, SiderealMode, RiseTransitFlag;
var init_enums = __esm({
  "../core/dist/enums.js"() {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.NumberOfPlanets = exports.PlanetaryMoonOffset = exports.AsteroidOffset = exports.RiseTransitFlag = exports.SiderealMode = exports.CommonEclipseTypes = exports.EclipseType = exports.CommonCalculationFlags = exports.CalculationFlag = exports.HousePoint = exports.HouseSystem = exports.FictitiousPlanet = exports.Asteroid = exports.LunarPoint = exports.Planet = exports.CalendarType = void 0;
    (function(CalendarType3) {
      CalendarType3[CalendarType3["Julian"] = 0] = "Julian";
      CalendarType3[CalendarType3["Gregorian"] = 1] = "Gregorian";
    })(CalendarType || (exports.CalendarType = CalendarType = {}));
    (function(Planet3) {
      Planet3[Planet3["Sun"] = 0] = "Sun";
      Planet3[Planet3["Moon"] = 1] = "Moon";
      Planet3[Planet3["Mercury"] = 2] = "Mercury";
      Planet3[Planet3["Venus"] = 3] = "Venus";
      Planet3[Planet3["Mars"] = 4] = "Mars";
      Planet3[Planet3["Jupiter"] = 5] = "Jupiter";
      Planet3[Planet3["Saturn"] = 6] = "Saturn";
      Planet3[Planet3["Uranus"] = 7] = "Uranus";
      Planet3[Planet3["Neptune"] = 8] = "Neptune";
      Planet3[Planet3["Pluto"] = 9] = "Pluto";
      Planet3[Planet3["Earth"] = 14] = "Earth";
      Planet3[Planet3["EclipticNutation"] = -1] = "EclipticNutation";
      Planet3[Planet3["FixedStar"] = -10] = "FixedStar";
    })(Planet || (exports.Planet = Planet = {}));
    (function(LunarPoint2) {
      LunarPoint2[LunarPoint2["MeanNode"] = 10] = "MeanNode";
      LunarPoint2[LunarPoint2["TrueNode"] = 11] = "TrueNode";
      LunarPoint2[LunarPoint2["MeanApogee"] = 12] = "MeanApogee";
      LunarPoint2[LunarPoint2["OsculatingApogee"] = 13] = "OsculatingApogee";
      LunarPoint2[LunarPoint2["InterpolatedApogee"] = 21] = "InterpolatedApogee";
      LunarPoint2[LunarPoint2["InterpolatedPerigee"] = 22] = "InterpolatedPerigee";
    })(LunarPoint || (exports.LunarPoint = LunarPoint = {}));
    (function(Asteroid2) {
      Asteroid2[Asteroid2["Chiron"] = 15] = "Chiron";
      Asteroid2[Asteroid2["Pholus"] = 16] = "Pholus";
      Asteroid2[Asteroid2["Ceres"] = 17] = "Ceres";
      Asteroid2[Asteroid2["Pallas"] = 18] = "Pallas";
      Asteroid2[Asteroid2["Juno"] = 19] = "Juno";
      Asteroid2[Asteroid2["Vesta"] = 20] = "Vesta";
    })(Asteroid || (exports.Asteroid = Asteroid = {}));
    (function(FictitiousPlanet2) {
      FictitiousPlanet2[FictitiousPlanet2["Cupido"] = 40] = "Cupido";
      FictitiousPlanet2[FictitiousPlanet2["Hades"] = 41] = "Hades";
      FictitiousPlanet2[FictitiousPlanet2["Zeus"] = 42] = "Zeus";
      FictitiousPlanet2[FictitiousPlanet2["Kronos"] = 43] = "Kronos";
      FictitiousPlanet2[FictitiousPlanet2["Apollon"] = 44] = "Apollon";
      FictitiousPlanet2[FictitiousPlanet2["Admetos"] = 45] = "Admetos";
      FictitiousPlanet2[FictitiousPlanet2["Vulkanus"] = 46] = "Vulkanus";
      FictitiousPlanet2[FictitiousPlanet2["Poseidon"] = 47] = "Poseidon";
      FictitiousPlanet2[FictitiousPlanet2["Isis"] = 48] = "Isis";
      FictitiousPlanet2[FictitiousPlanet2["Nibiru"] = 49] = "Nibiru";
      FictitiousPlanet2[FictitiousPlanet2["Harrington"] = 50] = "Harrington";
      FictitiousPlanet2[FictitiousPlanet2["NeptuneLeverrier"] = 51] = "NeptuneLeverrier";
      FictitiousPlanet2[FictitiousPlanet2["NeptuneAdams"] = 52] = "NeptuneAdams";
      FictitiousPlanet2[FictitiousPlanet2["PlutoLowell"] = 53] = "PlutoLowell";
      FictitiousPlanet2[FictitiousPlanet2["PlutoPickering"] = 54] = "PlutoPickering";
      FictitiousPlanet2[FictitiousPlanet2["Vulcan"] = 55] = "Vulcan";
      FictitiousPlanet2[FictitiousPlanet2["WhiteMoon"] = 56] = "WhiteMoon";
      FictitiousPlanet2[FictitiousPlanet2["Proserpina"] = 57] = "Proserpina";
      FictitiousPlanet2[FictitiousPlanet2["Waldemath"] = 58] = "Waldemath";
    })(FictitiousPlanet || (exports.FictitiousPlanet = FictitiousPlanet = {}));
    (function(HouseSystem3) {
      HouseSystem3["Placidus"] = "P";
      HouseSystem3["Koch"] = "K";
      HouseSystem3["Porphyrius"] = "O";
      HouseSystem3["Regiomontanus"] = "R";
      HouseSystem3["Campanus"] = "C";
      HouseSystem3["Equal"] = "A";
      HouseSystem3["VehlowEqual"] = "V";
      HouseSystem3["WholeSign"] = "W";
      HouseSystem3["Meridian"] = "X";
      HouseSystem3["Azimuthal"] = "H";
      HouseSystem3["PolichPage"] = "T";
      HouseSystem3["Alcabitus"] = "B";
      HouseSystem3["Morinus"] = "M";
    })(HouseSystem || (exports.HouseSystem = HouseSystem = {}));
    (function(HousePoint3) {
      HousePoint3[HousePoint3["Ascendant"] = 0] = "Ascendant";
      HousePoint3[HousePoint3["MC"] = 1] = "MC";
      HousePoint3[HousePoint3["ARMC"] = 2] = "ARMC";
      HousePoint3[HousePoint3["Vertex"] = 3] = "Vertex";
      HousePoint3[HousePoint3["EquatorialAscendant"] = 4] = "EquatorialAscendant";
      HousePoint3[HousePoint3["CoAscendant1"] = 5] = "CoAscendant1";
      HousePoint3[HousePoint3["CoAscendant2"] = 6] = "CoAscendant2";
      HousePoint3[HousePoint3["PolarAscendant"] = 7] = "PolarAscendant";
    })(HousePoint || (exports.HousePoint = HousePoint = {}));
    (function(CalculationFlag3) {
      CalculationFlag3[CalculationFlag3["JPLEphemeris"] = 1] = "JPLEphemeris";
      CalculationFlag3[CalculationFlag3["SwissEphemeris"] = 2] = "SwissEphemeris";
      CalculationFlag3[CalculationFlag3["MoshierEphemeris"] = 4] = "MoshierEphemeris";
      CalculationFlag3[CalculationFlag3["Heliocentric"] = 8] = "Heliocentric";
      CalculationFlag3[CalculationFlag3["TruePositions"] = 16] = "TruePositions";
      CalculationFlag3[CalculationFlag3["J2000"] = 32] = "J2000";
      CalculationFlag3[CalculationFlag3["NoNutation"] = 64] = "NoNutation";
      CalculationFlag3[CalculationFlag3["Speed3"] = 128] = "Speed3";
      CalculationFlag3[CalculationFlag3["Speed"] = 256] = "Speed";
      CalculationFlag3[CalculationFlag3["NoGravitationalDeflection"] = 512] = "NoGravitationalDeflection";
      CalculationFlag3[CalculationFlag3["NoAberration"] = 1024] = "NoAberration";
      CalculationFlag3[CalculationFlag3["Equatorial"] = 2048] = "Equatorial";
      CalculationFlag3[CalculationFlag3["XYZ"] = 4096] = "XYZ";
      CalculationFlag3[CalculationFlag3["Radians"] = 8192] = "Radians";
      CalculationFlag3[CalculationFlag3["Barycentric"] = 16384] = "Barycentric";
      CalculationFlag3[CalculationFlag3["Topocentric"] = 32768] = "Topocentric";
      CalculationFlag3[CalculationFlag3["Sidereal"] = 65536] = "Sidereal";
      CalculationFlag3[CalculationFlag3["ICRS"] = 131072] = "ICRS";
      CalculationFlag3[CalculationFlag3["DpsidepsIAU1980"] = 262144] = "DpsidepsIAU1980";
      CalculationFlag3[CalculationFlag3["JPLHorizons"] = 524288] = "JPLHorizons";
      CalculationFlag3[CalculationFlag3["JPLHorizonsApprox"] = 1048576] = "JPLHorizonsApprox";
    })(CalculationFlag || (exports.CalculationFlag = CalculationFlag = {}));
    exports.CommonCalculationFlags = {
      /** Astrometric positions (no aberration or gravitational deflection) */
      Astrometric: CalculationFlag.NoAberration | CalculationFlag.NoGravitationalDeflection,
      /** Default flags for Swiss Ephemeris with speed */
      DefaultSwissEphemeris: CalculationFlag.SwissEphemeris | CalculationFlag.Speed,
      /** Default flags for Moshier with speed */
      DefaultMoshier: CalculationFlag.MoshierEphemeris | CalculationFlag.Speed
    };
    (function(EclipseType2) {
      EclipseType2[EclipseType2["Central"] = 1] = "Central";
      EclipseType2[EclipseType2["NonCentral"] = 2] = "NonCentral";
      EclipseType2[EclipseType2["Total"] = 4] = "Total";
      EclipseType2[EclipseType2["Annular"] = 8] = "Annular";
      EclipseType2[EclipseType2["Partial"] = 16] = "Partial";
      EclipseType2[EclipseType2["AnnularTotal"] = 32] = "AnnularTotal";
      EclipseType2[EclipseType2["Penumbral"] = 64] = "Penumbral";
    })(EclipseType || (exports.EclipseType = EclipseType = {}));
    exports.CommonEclipseTypes = {
      /** All types of solar eclipses */
      AllSolar: EclipseType.Central | EclipseType.NonCentral | EclipseType.Total | EclipseType.Annular | EclipseType.Partial | EclipseType.AnnularTotal,
      /** All types of lunar eclipses */
      AllLunar: EclipseType.Total | EclipseType.Partial | EclipseType.Penumbral
    };
    (function(SiderealMode2) {
      SiderealMode2[SiderealMode2["FaganBradley"] = 0] = "FaganBradley";
      SiderealMode2[SiderealMode2["Lahiri"] = 1] = "Lahiri";
      SiderealMode2[SiderealMode2["DeLuce"] = 2] = "DeLuce";
      SiderealMode2[SiderealMode2["Raman"] = 3] = "Raman";
      SiderealMode2[SiderealMode2["Ushashashi"] = 4] = "Ushashashi";
      SiderealMode2[SiderealMode2["Krishnamurti"] = 5] = "Krishnamurti";
      SiderealMode2[SiderealMode2["DjwhalKhul"] = 6] = "DjwhalKhul";
      SiderealMode2[SiderealMode2["Yukteshwar"] = 7] = "Yukteshwar";
      SiderealMode2[SiderealMode2["JNBhasin"] = 8] = "JNBhasin";
      SiderealMode2[SiderealMode2["BabylKugler1"] = 9] = "BabylKugler1";
      SiderealMode2[SiderealMode2["BabylKugler2"] = 10] = "BabylKugler2";
      SiderealMode2[SiderealMode2["BabylKugler3"] = 11] = "BabylKugler3";
      SiderealMode2[SiderealMode2["BabylHuber"] = 12] = "BabylHuber";
      SiderealMode2[SiderealMode2["BabylEtPSC"] = 13] = "BabylEtPSC";
      SiderealMode2[SiderealMode2["Aldebaran15Tau"] = 14] = "Aldebaran15Tau";
      SiderealMode2[SiderealMode2["Hipparchos"] = 15] = "Hipparchos";
      SiderealMode2[SiderealMode2["Sassanian"] = 16] = "Sassanian";
      SiderealMode2[SiderealMode2["GalacticCenter0Sag"] = 17] = "GalacticCenter0Sag";
      SiderealMode2[SiderealMode2["J2000"] = 18] = "J2000";
      SiderealMode2[SiderealMode2["J1900"] = 19] = "J1900";
      SiderealMode2[SiderealMode2["B1950"] = 20] = "B1950";
      SiderealMode2[SiderealMode2["SuryaSiddhanta"] = 21] = "SuryaSiddhanta";
      SiderealMode2[SiderealMode2["SuryaSiddhantaMeanSun"] = 22] = "SuryaSiddhantaMeanSun";
      SiderealMode2[SiderealMode2["Aryabhata"] = 23] = "Aryabhata";
      SiderealMode2[SiderealMode2["AryabhataMeanSun"] = 24] = "AryabhataMeanSun";
      SiderealMode2[SiderealMode2["SSRevati"] = 25] = "SSRevati";
      SiderealMode2[SiderealMode2["SSCitra"] = 26] = "SSCitra";
      SiderealMode2[SiderealMode2["TrueCitra"] = 27] = "TrueCitra";
      SiderealMode2[SiderealMode2["TrueRevati"] = 28] = "TrueRevati";
      SiderealMode2[SiderealMode2["TruePushya"] = 29] = "TruePushya";
      SiderealMode2[SiderealMode2["GalacticCenterGilBrand"] = 30] = "GalacticCenterGilBrand";
      SiderealMode2[SiderealMode2["GalacticEquatorIAU1958"] = 31] = "GalacticEquatorIAU1958";
      SiderealMode2[SiderealMode2["GalacticEquator"] = 32] = "GalacticEquator";
      SiderealMode2[SiderealMode2["GalacticEquatorMidMula"] = 33] = "GalacticEquatorMidMula";
      SiderealMode2[SiderealMode2["Skydram"] = 34] = "Skydram";
      SiderealMode2[SiderealMode2["TrueMula"] = 35] = "TrueMula";
      SiderealMode2[SiderealMode2["DhruvaGalCenterMulaWilhelm"] = 36] = "DhruvaGalCenterMulaWilhelm";
      SiderealMode2[SiderealMode2["Aryabhata522"] = 37] = "Aryabhata522";
      SiderealMode2[SiderealMode2["BabylBritton"] = 38] = "BabylBritton";
      SiderealMode2[SiderealMode2["UserDefined"] = 255] = "UserDefined";
    })(SiderealMode || (exports.SiderealMode = SiderealMode = {}));
    (function(RiseTransitFlag2) {
      RiseTransitFlag2[RiseTransitFlag2["Rise"] = 1] = "Rise";
      RiseTransitFlag2[RiseTransitFlag2["Set"] = 2] = "Set";
      RiseTransitFlag2[RiseTransitFlag2["UpperTransit"] = 4] = "UpperTransit";
      RiseTransitFlag2[RiseTransitFlag2["LowerTransit"] = 8] = "LowerTransit";
    })(RiseTransitFlag || (exports.RiseTransitFlag = RiseTransitFlag = {}));
    exports.AsteroidOffset = 1e4;
    exports.PlanetaryMoonOffset = 9e3;
    exports.NumberOfPlanets = 23;
  }
});

// ../core/dist/implementations.js
var implementations_exports = {};
var enums_js_1, _LunarEclipseImpl, LunarEclipseImpl, _SolarEclipseImpl, SolarEclipseImpl, _DateTimeImpl, DateTimeImpl;
var init_implementations = __esm({
  "../core/dist/implementations.js"() {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.DateTimeImpl = exports.SolarEclipseImpl = exports.LunarEclipseImpl = void 0;
    enums_js_1 = (init_enums(), __toCommonJS(enums_exports));
    _LunarEclipseImpl = class _LunarEclipseImpl {
      constructor(type, maximum, partialBegin, partialEnd, totalBegin, totalEnd, penumbralBegin, penumbralEnd) {
        this.type = type;
        this.maximum = maximum;
        this.partialBegin = partialBegin;
        this.partialEnd = partialEnd;
        this.totalBegin = totalBegin;
        this.totalEnd = totalEnd;
        this.penumbralBegin = penumbralBegin;
        this.penumbralEnd = penumbralEnd;
      }
      isTotal() {
        return (this.type & enums_js_1.EclipseType.Total) !== 0;
      }
      isPartial() {
        return (this.type & enums_js_1.EclipseType.Partial) !== 0;
      }
      isPenumbralOnly() {
        return (this.type & enums_js_1.EclipseType.Penumbral) !== 0 && (this.type & (enums_js_1.EclipseType.Total | enums_js_1.EclipseType.Partial)) === 0;
      }
      getTotalityDuration() {
        if (!this.isTotal() || this.totalBegin === 0 || this.totalEnd === 0) {
          return 0;
        }
        const duration = (this.totalEnd - this.totalBegin) * 24;
        return duration > 0 ? duration : 0;
      }
      getPartialDuration() {
        if (this.partialBegin === 0 || this.partialEnd === 0) {
          return 0;
        }
        const duration = (this.partialEnd - this.partialBegin) * 24;
        return duration > 0 ? duration : 0;
      }
      getTotalDuration() {
        if (this.penumbralBegin === 0 || this.penumbralEnd === 0) {
          return 0;
        }
        const duration = (this.penumbralEnd - this.penumbralBegin) * 24;
        return duration > 0 ? duration : 0;
      }
    };
    __name(_LunarEclipseImpl, "LunarEclipseImpl");
    LunarEclipseImpl = _LunarEclipseImpl;
    exports.LunarEclipseImpl = LunarEclipseImpl;
    _SolarEclipseImpl = class _SolarEclipseImpl {
      constructor(type, maximum, partialBegin, partialEnd, centralBegin, centralEnd, centerLineBegin, centerLineEnd) {
        this.type = type;
        this.maximum = maximum;
        this.partialBegin = partialBegin;
        this.partialEnd = partialEnd;
        this.centralBegin = centralBegin;
        this.centralEnd = centralEnd;
        this.centerLineBegin = centerLineBegin;
        this.centerLineEnd = centerLineEnd;
      }
      isTotal() {
        return (this.type & enums_js_1.EclipseType.Total) !== 0;
      }
      isAnnular() {
        return (this.type & enums_js_1.EclipseType.Annular) !== 0;
      }
      isHybrid() {
        return (this.type & enums_js_1.EclipseType.AnnularTotal) !== 0;
      }
      isPartial() {
        return (this.type & enums_js_1.EclipseType.Partial) !== 0;
      }
      isCentral() {
        return (this.type & enums_js_1.EclipseType.Central) !== 0;
      }
      isNonCentral() {
        return (this.type & enums_js_1.EclipseType.NonCentral) !== 0;
      }
    };
    __name(_SolarEclipseImpl, "SolarEclipseImpl");
    SolarEclipseImpl = _SolarEclipseImpl;
    exports.SolarEclipseImpl = SolarEclipseImpl;
    _DateTimeImpl = class _DateTimeImpl {
      constructor(year, month, day, hour, calendarType = enums_js_1.CalendarType.Gregorian) {
        this.year = year;
        this.month = month;
        this.day = day;
        this.hour = hour;
        this.calendarType = calendarType;
      }
      toISOString() {
        const hours = Math.floor(this.hour);
        const minutes = Math.floor((this.hour - hours) * 60);
        const seconds = Math.floor(((this.hour - hours) * 60 - minutes) * 60);
        const milliseconds = Math.floor((((this.hour - hours) * 60 - minutes) * 60 - seconds) * 1e3);
        const yearStr = Math.abs(this.year).toString().padStart(4, "0");
        const yearSign = this.year < 0 ? "-" : "";
        const monthStr = this.month.toString().padStart(2, "0");
        const dayStr = this.day.toString().padStart(2, "0");
        const hoursStr = hours.toString().padStart(2, "0");
        const minutesStr = minutes.toString().padStart(2, "0");
        const secondsStr = seconds.toString().padStart(2, "0");
        const msStr = milliseconds.toString().padStart(3, "0");
        return `${yearSign}${yearStr}-${monthStr}-${dayStr}T${hoursStr}:${minutesStr}:${secondsStr}.${msStr}Z`;
      }
      toString() {
        const calType = this.calendarType === enums_js_1.CalendarType.Gregorian ? "Gregorian" : "Julian";
        const yearStr = this.year < 0 ? `${Math.abs(this.year)} BCE` : this.year.toString();
        return `${yearStr}-${this.month.toString().padStart(2, "0")}-${this.day.toString().padStart(2, "0")} ${this.hour.toFixed(6)} hours (${calType})`;
      }
    };
    __name(_DateTimeImpl, "DateTimeImpl");
    DateTimeImpl = _DateTimeImpl;
    exports.DateTimeImpl = DateTimeImpl;
  }
});

// ../core/dist/flags.js
var flags_exports = {};
function normalizeFlags(input) {
  if (typeof input === "number") {
    return input;
  }
  if (input instanceof CalculationFlags) {
    return input.toNumber();
  }
  if (Array.isArray(input)) {
    return CalculationFlags.from(...input).toNumber();
  }
  return input;
}
function normalizeEclipseTypes(input) {
  if (typeof input === "number") {
    return input;
  }
  if (input instanceof EclipseTypeFlags) {
    return input.toNumber();
  }
  if (Array.isArray(input)) {
    return EclipseTypeFlags.from(...input).toNumber();
  }
  return input;
}
var enums_js_12, _CalculationFlags, CalculationFlags, _EclipseTypeFlags, EclipseTypeFlags;
var init_flags = __esm({
  "../core/dist/flags.js"() {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.EclipseTypeFlags = exports.CalculationFlags = void 0;
    exports.normalizeFlags = normalizeFlags;
    exports.normalizeEclipseTypes = normalizeEclipseTypes;
    enums_js_12 = (init_enums(), __toCommonJS(enums_exports));
    _CalculationFlags = class _CalculationFlags {
      constructor(initialFlags) {
        this.flags = 0;
        if (initialFlags !== void 0) {
          this.add(initialFlags);
        }
      }
      /**
       * Add one or more flags to the current set
       * @param flag - Single flag or array of flags to add
       * @returns this (for method chaining)
       */
      add(flag) {
        if (Array.isArray(flag)) {
          flag.forEach((f) => this.flags |= f);
        } else {
          this.flags |= flag;
        }
        return this;
      }
      /**
       * Remove one or more flags from the current set
       * @param flag - Single flag or array of flags to remove
       * @returns this (for method chaining)
       */
      remove(flag) {
        if (Array.isArray(flag)) {
          flag.forEach((f) => this.flags &= ~f);
        } else {
          this.flags &= ~flag;
        }
        return this;
      }
      /**
       * Check if a specific flag is set
       * @param flag - Flag to check
       * @returns true if the flag is set
       */
      has(flag) {
        return (this.flags & flag) === flag;
      }
      /**
       * Convert to raw number for passing to C library
       * @returns The numeric representation of all combined flags
       */
      toNumber() {
        return this.flags;
      }
      /**
       * Create a new CalculationFlags instance from one or more flags
       * @param flags - Flags to combine
       * @returns New CalculationFlags instance
       */
      static from(...flags) {
        return new _CalculationFlags(flags);
      }
      /**
       * Common preset: Swiss Ephemeris with speed calculation
       */
      static get swissEphemerisWithSpeed() {
        return _CalculationFlags.from(enums_js_12.CalculationFlag.SwissEphemeris, enums_js_12.CalculationFlag.Speed);
      }
      /**
       * Common preset: Moshier ephemeris with speed calculation
       */
      static get moshierWithSpeed() {
        return _CalculationFlags.from(enums_js_12.CalculationFlag.MoshierEphemeris, enums_js_12.CalculationFlag.Speed);
      }
      /**
       * Common preset: Astrometric positions (no aberration or light deflection)
       */
      static get astrometric() {
        return _CalculationFlags.from(enums_js_12.CalculationFlag.SwissEphemeris, enums_js_12.CalculationFlag.NoAberration, enums_js_12.CalculationFlag.NoGravitationalDeflection);
      }
      /**
       * Common preset: Heliocentric positions
       */
      static get heliocentric() {
        return _CalculationFlags.from(enums_js_12.CalculationFlag.SwissEphemeris, enums_js_12.CalculationFlag.Heliocentric);
      }
      /**
       * Common preset: Topocentric positions
       */
      static get topocentric() {
        return _CalculationFlags.from(enums_js_12.CalculationFlag.SwissEphemeris, enums_js_12.CalculationFlag.Topocentric);
      }
      /**
       * Common preset: Equatorial coordinates (RA/Dec)
       */
      static get equatorial() {
        return _CalculationFlags.from(enums_js_12.CalculationFlag.SwissEphemeris, enums_js_12.CalculationFlag.Equatorial, enums_js_12.CalculationFlag.Speed);
      }
    };
    __name(_CalculationFlags, "CalculationFlags");
    CalculationFlags = _CalculationFlags;
    exports.CalculationFlags = CalculationFlags;
    _EclipseTypeFlags = class _EclipseTypeFlags {
      constructor(initialFlags) {
        this.flags = 0;
        if (initialFlags !== void 0) {
          this.add(initialFlags);
        }
      }
      /**
       * Add one or more eclipse types to the filter
       * @param flag - Single type or array of types to add
       * @returns this (for method chaining)
       */
      add(flag) {
        if (Array.isArray(flag)) {
          flag.forEach((f) => this.flags |= f);
        } else {
          this.flags |= flag;
        }
        return this;
      }
      /**
       * Check if a specific eclipse type is in the filter
       * @param flag - Eclipse type to check
       * @returns true if the type is included
       */
      has(flag) {
        return (this.flags & flag) === flag;
      }
      /**
       * Convert to raw number for passing to C library
       * @returns The numeric representation of all combined types
       */
      toNumber() {
        return this.flags;
      }
      /**
       * Create a new EclipseTypeFlags instance from one or more types
       * @param flags - Eclipse types to combine
       * @returns New EclipseTypeFlags instance
       */
      static from(...flags) {
        return new _EclipseTypeFlags(flags);
      }
      /**
       * Preset: All solar eclipse types
       */
      static get allSolar() {
        return new _EclipseTypeFlags([
          enums_js_12.EclipseType.Central,
          enums_js_12.EclipseType.NonCentral,
          enums_js_12.EclipseType.Total,
          enums_js_12.EclipseType.Annular,
          enums_js_12.EclipseType.Partial,
          enums_js_12.EclipseType.AnnularTotal
        ]);
      }
      /**
       * Preset: All lunar eclipse types
       */
      static get allLunar() {
        return new _EclipseTypeFlags([
          enums_js_12.EclipseType.Total,
          enums_js_12.EclipseType.Partial,
          enums_js_12.EclipseType.Penumbral
        ]);
      }
      /**
       * Preset: Only total eclipses
       */
      static get totalOnly() {
        return _EclipseTypeFlags.from(enums_js_12.EclipseType.Total);
      }
      /**
       * Preset: Total and partial eclipses (no penumbral)
       */
      static get totalAndPartial() {
        return _EclipseTypeFlags.from(enums_js_12.EclipseType.Total, enums_js_12.EclipseType.Partial);
      }
    };
    __name(_EclipseTypeFlags, "EclipseTypeFlags");
    EclipseTypeFlags = _EclipseTypeFlags;
    exports.EclipseTypeFlags = EclipseTypeFlags;
    __name(normalizeFlags, "normalizeFlags");
    __name(normalizeEclipseTypes, "normalizeEclipseTypes");
  }
});

// ../core/dist/index.js
Object.defineProperty(exports, "__esModule", { value: true });
exports.normalizeEclipseTypes = exports.normalizeFlags = exports.EclipseTypeFlags = exports.CalculationFlags = exports.DateTimeImpl = exports.SolarEclipseImpl = exports.LunarEclipseImpl = exports.NumberOfPlanets = exports.PlanetaryMoonOffset = exports.AsteroidOffset = exports.CommonEclipseTypes = exports.CommonCalculationFlags = exports.RiseTransitFlag = exports.SiderealMode = exports.EclipseType = exports.CalculationFlag = exports.HousePoint = exports.HouseSystem = exports.FictitiousPlanet = exports.Asteroid = exports.LunarPoint = exports.Planet = exports.CalendarType = void 0;
var enums_js_13 = (init_enums(), __toCommonJS(enums_exports));
Object.defineProperty(exports, "CalendarType", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.CalendarType;
}, "get") });
Object.defineProperty(exports, "Planet", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.Planet;
}, "get") });
Object.defineProperty(exports, "LunarPoint", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.LunarPoint;
}, "get") });
Object.defineProperty(exports, "Asteroid", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.Asteroid;
}, "get") });
Object.defineProperty(exports, "FictitiousPlanet", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.FictitiousPlanet;
}, "get") });
Object.defineProperty(exports, "HouseSystem", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.HouseSystem;
}, "get") });
Object.defineProperty(exports, "HousePoint", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.HousePoint;
}, "get") });
Object.defineProperty(exports, "CalculationFlag", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.CalculationFlag;
}, "get") });
Object.defineProperty(exports, "EclipseType", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.EclipseType;
}, "get") });
Object.defineProperty(exports, "SiderealMode", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.SiderealMode;
}, "get") });
Object.defineProperty(exports, "RiseTransitFlag", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.RiseTransitFlag;
}, "get") });
Object.defineProperty(exports, "CommonCalculationFlags", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.CommonCalculationFlags;
}, "get") });
Object.defineProperty(exports, "CommonEclipseTypes", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.CommonEclipseTypes;
}, "get") });
Object.defineProperty(exports, "AsteroidOffset", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.AsteroidOffset;
}, "get") });
Object.defineProperty(exports, "PlanetaryMoonOffset", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.PlanetaryMoonOffset;
}, "get") });
Object.defineProperty(exports, "NumberOfPlanets", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return enums_js_13.NumberOfPlanets;
}, "get") });
var implementations_js_1 = (init_implementations(), __toCommonJS(implementations_exports));
Object.defineProperty(exports, "LunarEclipseImpl", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return implementations_js_1.LunarEclipseImpl;
}, "get") });
Object.defineProperty(exports, "SolarEclipseImpl", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return implementations_js_1.SolarEclipseImpl;
}, "get") });
Object.defineProperty(exports, "DateTimeImpl", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return implementations_js_1.DateTimeImpl;
}, "get") });
var flags_js_1 = (init_flags(), __toCommonJS(flags_exports));
Object.defineProperty(exports, "CalculationFlags", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return flags_js_1.CalculationFlags;
}, "get") });
Object.defineProperty(exports, "EclipseTypeFlags", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return flags_js_1.EclipseTypeFlags;
}, "get") });
Object.defineProperty(exports, "normalizeFlags", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return flags_js_1.normalizeFlags;
}, "get") });
Object.defineProperty(exports, "normalizeEclipseTypes", { enumerable: true, get: /* @__PURE__ */ __name(function() {
  return flags_js_1.normalizeEclipseTypes;
}, "get") });

// src/swisseph-browser.ts
var _SwissEphemeris = class _SwissEphemeris {
  constructor() {
    this.module = null;
    this.ready = false;
  }
  /**
   * Initialize the WebAssembly module
   *
   * This must be called before using any other methods.
   * The WASM file is automatically loaded from the same directory as the JS bundle.
   *
   * @param wasmPath - Optional custom path to swisseph.wasm file (for advanced use cases)
   *
   * @example
   * const swe = new SwissEphemeris();
   * await swe.init();
   * console.log(swe.version());
   */
  async init(wasmPath) {
    if (this.ready) return;
    const SwissEphModuleImport = await import("./swisseph.js");
    let SwissEphModuleFactory;
    if (typeof SwissEphModuleImport.default === "function") {
      SwissEphModuleFactory = SwissEphModuleImport.default;
    } else if (typeof SwissEphModuleImport === "function") {
      SwissEphModuleFactory = SwissEphModuleImport;
    } else if (SwissEphModuleImport.default) {
      SwissEphModuleFactory = SwissEphModuleImport.default;
    } else {
      SwissEphModuleFactory = SwissEphModuleImport.SwissEphModule || SwissEphModuleImport;
    }
    if (typeof SwissEphModuleFactory !== "function") {
      throw new Error("Failed to load WASM module: SwissEphModule factory function not found");
    }
    let resolvedWasmPath = wasmPath;
    if (!resolvedWasmPath) {
      try {
        resolvedWasmPath = new URL("./swisseph.wasm", import.meta.url).href;
      } catch (e) {
        resolvedWasmPath = "swisseph.wasm";
      }
    }
    this.module = await SwissEphModuleFactory({
      locateFile: /* @__PURE__ */ __name((path, prefix) => {
        if (path === "swisseph.wasm") {
          return resolvedWasmPath;
        }
        return prefix ? prefix + path : path;
      }, "locateFile")
    });
    this._wrapFunctions();
    this.ready = true;
    console.log("Swiss Ephemeris WASM initialized:", this.version());
  }
  /**
   * Wrap C functions for easier calling
   */
  _wrapFunctions() {
    const m = this.module;
    this._julday = m.cwrap(
      "swe_julday_wrap",
      "number",
      ["number", "number", "number", "number", "number"]
    );
    this._getPlanetName = m.cwrap(
      "swe_get_planet_name_wrap",
      "string",
      ["number"]
    );
    this._close = m.cwrap("swe_close_wrap", null, []);
    this._version = m.cwrap("swe_version_wrap", "string", []);
  }
  /**
   * Check if the module is ready for use
   * @throws Error if not initialized
   */
  _checkReady() {
    if (!this.ready) {
      throw new Error(
        "SwissEphemeris not initialized. Call await swe.init() first."
      );
    }
  }
  /**
   * Get Swiss Ephemeris version string
   */
  version() {
    this._checkReady();
    return this._version();
  }
  /**
   * Set ephemeris file path
   *
   * Note: This is typically not used in the browser version as we use
   * the built-in Moshier ephemeris.
   *
   * @param path - Path to ephemeris files
   */
  setEphemerisPath(path) {
    this._checkReady();
    const m = this.module;
    const pathPtr = m.allocateUTF8(path || "");
    m.ccall("swe_set_ephe_path_wrap", null, ["number"], [pathPtr]);
    m._free(pathPtr);
  }
  /**
   * Load standard Swiss Ephemeris data files from jsDelivr CDN
   *
   * Simple one-line method to download standard ephemeris files (~2MB).
   * After loading, you can use CalculationFlag.SwissEphemeris for maximum precision.
   *
   * @example
   * // Simple: Load all standard files
   * await swe.loadStandardEphemeris();
   *
   * // Then use Swiss Ephemeris for calculations
   * const sun = swe.calculatePosition(jd, Planet.Sun, CalculationFlag.SwissEphemeris);
   */
  async loadStandardEphemeris() {
    const CDN_BASE = "https://cdn.jsdelivr.net/gh/aloistr/swisseph/ephe";
    await this.loadEphemerisFiles([
      { name: "sepl_18.se1", url: `${CDN_BASE}/sepl_18.se1` },
      { name: "semo_18.se1", url: `${CDN_BASE}/semo_18.se1` },
      { name: "seas_18.se1", url: `${CDN_BASE}/seas_18.se1` }
    ]);
  }
  /**
   * Load Swiss Ephemeris data files from URLs
   *
   * Downloads ephemeris files and writes them to the virtual filesystem.
   * Use this for maximum precision calculations or custom file sources.
   *
   * @param files - Array of files to download with name and URL
   *
   * @example
   * // Load from custom CDN or server
   * await swe.loadEphemerisFiles([
   *   {
   *     name: 'sepl_18.se1',
   *     url: 'https://your-cdn.com/ephemeris/sepl_18.se1'
   *   },
   *   {
   *     name: 'semo_18.se1',
   *     url: 'https://your-cdn.com/ephemeris/semo_18.se1'
   *   }
   * ]);
   *
   * // Then use Swiss Ephemeris
   * const sun = swe.calculatePosition(jd, Planet.Sun, CalculationFlag.SwissEphemeris);
   */
  async loadEphemerisFiles(files) {
    this._checkReady();
    const m = this.module;
    try {
      m.FS.mkdir("/ephemeris");
    } catch (e) {
    }
    for (const file of files) {
      const response = await fetch(file.url);
      if (!response.ok) {
        throw new Error(`Failed to download ${file.name}: ${response.statusText}`);
      }
      const arrayBuffer = await response.arrayBuffer();
      const data = new Uint8Array(arrayBuffer);
      m.FS.writeFile(`/ephemeris/${file.name}`, data);
    }
    this.setEphemerisPath("/ephemeris");
  }
  /**
   * Calculate Julian day number from calendar date
   *
   * @param year - Year (negative for BCE)
   * @param month - Month (1-12)
   * @param day - Day (1-31)
   * @param hour - Hour as decimal (0.0-23.999...)
   * @param calendarType - Calendar system (default: Gregorian)
   * @returns Julian day number
   *
   * @example
   * const jd = swe.julianDay(2007, 3, 3);
   * console.log(jd); // 2454162.5
   */
  julianDay(year, month, day, hour = 0, calendarType = (void 0).Gregorian) {
    this._checkReady();
    if (!Number.isFinite(year) || !Number.isFinite(month) || !Number.isFinite(day) || !Number.isFinite(hour)) {
      throw new TypeError(
        `julianDay requires finite numbers. Received: year=${year}, month=${month}, day=${day}, hour=${hour}`
      );
    }
    return this._julday(year, month, day, hour, calendarType);
  }
  /**
   * Calculate Julian day number from a JavaScript Date object
   *
   * Convenience function that converts a JavaScript Date to Julian day number.
   * The Date is interpreted as UTC.
   *
   * @param date - JavaScript Date object (interpreted as UTC)
   * @param calendarType - Calendar system (default: Gregorian)
   * @returns Julian day number
   *
   * @example
   * // From Date object
   * const date = new Date('1990-05-15T14:30:00Z');
   * const jd = swe.dateToJulianDay(date);
   *
   * // From timestamp
   * const now = new Date();
   * const jdNow = swe.dateToJulianDay(now);
   *
   * // Equivalent to swe.julianDay(1990, 5, 15, 14.5)
   * const jd2 = swe.dateToJulianDay(new Date(Date.UTC(1990, 4, 15, 14, 30)));
   */
  dateToJulianDay(date, calendarType = 1) {
    this._checkReady();
    if (!(date instanceof Date)) {
      throw new TypeError("dateToJulianDay expects a Date object");
    }
    const year = date.getUTCFullYear();
    const month = date.getUTCMonth() + 1;
    const day = date.getUTCDate();
    const hours = date.getUTCHours();
    const minutes = date.getUTCMinutes();
    const seconds = date.getUTCSeconds();
    const milliseconds = date.getUTCMilliseconds();
    if (isNaN(year) || isNaN(month) || isNaN(day) || isNaN(hours)) {
      throw new TypeError(
        `Invalid Date object provided to dateToJulianDay. Date.toString() returned: "${date.toString()}". Please ensure the date is valid (e.g., avoid new Date("invalid")).`
      );
    }
    const decimalHours = hours + minutes / 60 + seconds / 3600 + milliseconds / 36e5;
    return this.julianDay(year, month, day, decimalHours, calendarType);
  }
  /**
   * Convert Julian day number to calendar date
   *
   * @param jd - Julian day number
   * @param calendarType - Calendar system (default: Gregorian)
   * @returns DateTime object
   *
   * @example
   * const date = swe.julianDayToDate(2454162.5);
   * console.log(date.toString());
   */
  julianDayToDate(jd, calendarType = (void 0).Gregorian) {
    this._checkReady();
    const m = this.module;
    const yearPtr = m._malloc(4);
    const monthPtr = m._malloc(4);
    const dayPtr = m._malloc(4);
    const hourPtr = m._malloc(8);
    m.ccall(
      "swe_revjul_wrap",
      null,
      ["number", "number", "number", "number", "number", "number"],
      [jd, calendarType, yearPtr, monthPtr, dayPtr, hourPtr]
    );
    const year = m.getValue(yearPtr, "i32");
    const month = m.getValue(monthPtr, "i32");
    const day = m.getValue(dayPtr, "i32");
    const hour = m.getValue(hourPtr, "double");
    m._free(yearPtr);
    m._free(monthPtr);
    m._free(dayPtr);
    m._free(hourPtr);
    return Array.of(year, month, day, hour, calendarType);
  }
  /**
   * Calculate planetary positions
   *
   * Note: Browser version uses Moshier ephemeris by default.
   *
   * @param julianDay - Julian day number in Universal Time
   * @param body - Celestial body to calculate
   * @param flags - Calculation flags (default: Moshier with speed)
   * @returns PlanetaryPosition object
   *
   * @example
   * const sun = swe.calculatePosition(jd, Planet.Sun);
   * console.log(`Sun: ${sun.longitude}°, ${sun.latitude}°`);
   *
   * const moon = swe.calculatePosition(
   *   jd,
   *   Planet.Moon,
   *   CalculationFlag.MoshierEphemeris | CalculationFlag.Speed
   * );
   */
  calculatePosition(julianDay, body, flags = (void 0).DefaultMoshier) {
    this._checkReady();
    const normalizedFlags = (flags);
    const m = this.module;
    const xxPtr = m._malloc(6 * 8);
    const serrPtr = m._malloc(256);
    const retflag = m.ccall(
      "swe_calc_ut_wrap",
      "number",
      ["number", "number", "number", "number", "number"],
      [julianDay, body, normalizedFlags, xxPtr, serrPtr]
    );
    if (retflag < 0) {
      const error = m.UTF8ToString(serrPtr);
      m._free(xxPtr);
      m._free(serrPtr);
      throw new Error(error);
    }
    const xx = [];
    for (let i = 0; i < 6; i++) {
      xx[i] = m.getValue(xxPtr + i * 8, "double");
    }
    m._free(xxPtr);
    m._free(serrPtr);
    return {
      longitude: xx[0],
      latitude: xx[1],
      distance: xx[2],
      longitudeSpeed: xx[3],
      latitudeSpeed: xx[4],
      distanceSpeed: xx[5],
      flags: retflag
    };
  }
  /**
   * Get celestial body name
   *
   * @param body - Celestial body identifier
   * @returns Name as a string
   *
   * @example
   * const name = swe.getCelestialBodyName(Planet.Mars);
   * console.log(name); // "Mars"
   */
  getCelestialBodyName(body) {
    this._checkReady();
    return this._getPlanetName(body);
  }
  /**
   * Find next lunar eclipse
   *
   * @param startJulianDay - Julian day to start search from
   * @param flags - Calculation flags (default: Moshier)
   * @param eclipseType - Filter by eclipse type (0 = all types)
   * @param backward - Search backward in time if true
   * @returns LunarEclipse object
   *
   * @example
   * const eclipse = swe.findNextLunarEclipse(jd);
   * console.log(`Is total: ${eclipse.isTotal()}`);
   * console.log(`Duration: ${eclipse.getTotalityDuration()} hours`);
   */
  findNextLunarEclipse(startJulianDay, flags = (void 0).MoshierEphemeris, eclipseType = 0, backward = false) {
    this._checkReady();
    const normalizedFlags = (flags);
    const normalizedEclipseType = (eclipseType);
    const m = this.module;
    const tretPtr = m._malloc(10 * 8);
    const serrPtr = m._malloc(256);
    const retflag = m.ccall(
      "swe_lun_eclipse_when_wrap",
      "number",
      ["number", "number", "number", "number", "number", "number"],
      [startJulianDay, normalizedFlags, normalizedEclipseType, tretPtr, backward ? 1 : 0, serrPtr]
    );
    if (retflag < 0) {
      const error = m.UTF8ToString(serrPtr);
      m._free(tretPtr);
      m._free(serrPtr);
      throw new Error(error);
    }
    const tret = [];
    for (let i = 0; i < 10; i++) {
      tret[i] = m.getValue(tretPtr + i * 8, "double");
    }
    m._free(tretPtr);
    m._free(serrPtr);
    return Array.of(
      retflag,
      tret[0],
      tret[1],
      tret[2],
      tret[3],
      tret[4],
      tret[5],
      tret[6]
    );
  }
  /**
   * Find next solar eclipse globally
   *
   * @param startJulianDay - Julian day to start search from
   * @param flags - Calculation flags (default: Moshier)
   * @param eclipseType - Filter by eclipse type (0 = all types)
   * @param backward - Search backward in time if true
   * @returns SolarEclipse object
   *
   * @example
   * const eclipse = swe.findNextSolarEclipse(jd);
   * console.log(`Is total: ${eclipse.isTotal()}`);
   * console.log(`Is central: ${eclipse.isCentral()}`);
   */
  findNextSolarEclipse(startJulianDay, flags = (void 0).MoshierEphemeris, eclipseType = 0, backward = false) {
    this._checkReady();
    const normalizedFlags = (flags);
    const normalizedEclipseType = (eclipseType);
    const m = this.module;
    const tretPtr = m._malloc(10 * 8);
    const serrPtr = m._malloc(256);
    const retflag = m.ccall(
      "swe_sol_eclipse_when_glob_wrap",
      "number",
      ["number", "number", "number", "number", "number", "number"],
      [startJulianDay, normalizedFlags, normalizedEclipseType, tretPtr, backward ? 1 : 0, serrPtr]
    );
    if (retflag < 0) {
      const error = m.UTF8ToString(serrPtr);
      m._free(tretPtr);
      m._free(serrPtr);
      throw new Error(error);
    }
    const tret = [];
    for (let i = 0; i < 10; i++) {
      tret[i] = m.getValue(tretPtr + i * 8, "double");
    }
    m._free(tretPtr);
    m._free(serrPtr);
    return Array.of(
      retflag,
      tret[0],
      tret[1],
      tret[2],
      tret[3],
      tret[4],
      tret[5],
      tret[6]
    );
  }
  /**
   * Calculate house cusps and angles
   *
   * @param julianDay - Julian day number in Universal Time
   * @param latitude - Geographic latitude
   * @param longitude - Geographic longitude
   * @param houseSystem - House system (default: Placidus)
   * @returns HouseData object
   *
   * @example
   * const houses = swe.calculateHouses(jd, 40.7128, -74.0060);
   * console.log(`Ascendant: ${houses.ascendant}°`);
   * console.log(`MC: ${houses.mc}°`);
   */
  calculateHouses(julianDay, latitude, longitude, houseSystem = "P") {
    this._checkReady();
    const m = this.module;
    const cuspsPtr = m._malloc(13 * 8);
    const ascmcPtr = m._malloc(10 * 8);
    const hsysCode = houseSystem.charCodeAt(0);
    m.ccall(
      "swe_houses_wrap",
      "number",
      ["number", "number", "number", "number", "number", "number"],
      [julianDay, latitude, longitude, hsysCode, cuspsPtr, ascmcPtr]
    );
    const cusps = [];
    for (let i = 0; i < 13; i++) {
      cusps[i] = m.getValue(cuspsPtr + i * 8, "double");
    }
    const ascmc = [];
    for (let i = 0; i < 10; i++) {
      ascmc[i] = m.getValue(ascmcPtr + i * 8, "double");
    }
    m._free(cuspsPtr);
    m._free(ascmcPtr);
    return {
      cusps,
      ascendant: ascmc[0],
      mc: ascmc[1],
      armc: ascmc[2],
      vertex: ascmc[3],
      equatorialAscendant: ascmc[4],
      coAscendant1: ascmc[5],
      coAscendant2: ascmc[6],
      polarAscendant: ascmc[7],
      houseSystem
    };
  }
  /**
   * Close Swiss Ephemeris and free resources
   */
  close() {
    if (this.ready) {
      this._close();
    }
  }
};
__name(_SwissEphemeris, "SwissEphemeris");
var SwissEphemeris = _SwissEphemeris;
var swisseph = new SwissEphemeris();
var swisseph_browser_default = SwissEphemeris;
if (typeof window !== "undefined") {
  window.SwissEphemeris = SwissEphemeris;
  window.swisseph = swisseph;
}
export {
  SwissEphemeris,
  swisseph_browser_default as default,
  swisseph
};
//# sourceMappingURL=swisseph-browser.js.map
