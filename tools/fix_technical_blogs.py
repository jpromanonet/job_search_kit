#!/usr/bin/env python3
"""Replace non-technical / job-search blog posts with technical ones."""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DAYS = ROOT / "data" / "days.json"

# day -> technical article pack
REPLACEMENTS: dict[int, dict[str, str]] = {
    5: {
        "blog_title": "Idempotencia en APIs: por qué importa en producción",
        "blog_angle": "Sin idempotencia, los reintentos convierten errores transitorios en duplicados y corrupción de datos.",
        "blog_draft": "problema/contexto → claves de idempotencia → límites y trampas → checklist. 600–900 palabras; anonimizá ejemplos.",
        "x_copy": "Nuevo artículo: “Idempotencia en APIs: por qué importa en producción”. Los reintentos sin diseño convierten fallas temporales en duplicados.[ARTICLE LINK] #Backend #APIs",
        "linkedin_copy": "Un timeout no es solo un error de red: sin idempotencia puede crear cargos o registros duplicados.\n\nNuevo artículo: “Idempotencia en APIs: por qué importa en producción.” Cómo diseñar claves, ventanas y contratos claros.\n\nLeé: [ARTICLE LINK]\n#SoftwareEngineering #Backend #APIs",
        "instagram_task": "☐ Story manual con el título, una takeaway sobre reintentos seguros, y [ARTICLE LINK]. Guardá evidencia en el Plan.",
    },
    6: {
        "blog_title": "Contratos de datos entre servicios: versionado sin dolor",
        "blog_angle": "El breaking change más caro suele ser un campo “inocente” que nadie versionó.",
        "blog_draft": "contexto → compatibilidad hacia atrás → evolución de schemas → ejemplos prácticos → CTA. 600–900 palabras.",
        "x_copy": "Hoy: “Contratos de datos entre servicios: versionado sin dolor”. Versionar el contrato evita incidentes silenciosos.[ARTICLE LINK] #DistributedSystems",
        "linkedin_copy": "Los equipos no pelean por JSON: pelean por contratos implícitos.\n\nNuevo artículo: “Contratos de datos entre servicios: versionado sin dolor.”\n\nLeé: [ARTICLE LINK]\n#SoftwareEngineering #Architecture #APIs",
        "instagram_task": "☐ Story con título + 1 tip de versionado + [ARTICLE LINK]. Guardá evidencia.",
    },
    7: {
        "blog_title": "Circuit breakers: fallar rápido para proteger el sistema",
        "blog_angle": "Un dependency lento puede tumbar todo el monolito o la malla si no cortás el circuito a tiempo.",
        "blog_draft": "síntoma → patrón circuit breaker → umbrales → observabilidad → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Circuit breakers: fallar rápido para proteger el sistema”. Mejor degradar que colapsar en cascada.[ARTICLE LINK] #Reliability",
        "linkedin_copy": "La resiliencia no es “reintentar más”: a veces es dejar de llamar.\n\nNuevo: “Circuit breakers: fallar rápido para proteger el sistema.”\n\n[ARTICLE LINK]\n#SRE #SoftwareEngineering #Reliability",
        "instagram_task": "☐ Story: título + idea de cascada de fallos + link. Guardá evidencia.",
    },
    8: {
        "blog_title": "Feature flags: desplegar sin apostar el uptime",
        "blog_angle": "Separar release de rollout permite probar en producción con blast radius controlado.",
        "blog_draft": "problema → flags vs branches largos → tipos de flags → gobernanza → CTA. 600–900 palabras.",
        "x_copy": "Nuevo: “Feature flags: desplegar sin apostar el uptime”. Release ≠ rollout.[ARTICLE LINK] #DevOps #Engineering",
        "linkedin_copy": "Si el deploy es el riesgo, el diseño del rollout está mal.\n\nArtículo: “Feature flags: desplegar sin apostar el uptime.”\n\nLeé: [ARTICLE LINK]\n#DevOps #SoftwareEngineering #CI_CD",
        "instagram_task": "☐ Story con título, takeaway “release ≠ rollout”, y [ARTICLE LINK].",
    },
    9: {
        "blog_title": "Índices SQL que aceleran… y los que solo ocupan disco",
        "blog_angle": "Un índice de más puede empeorar escrituras; uno de menos convierte un SELECT en un full scan.",
        "blog_draft": "contexto → lectura de EXPLAIN → índices compuestos → mantenimiento → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Índices SQL que aceleran… y los que solo ocupan disco”. Medí antes de indexar de más.[ARTICLE LINK] #SQL #Databases",
        "linkedin_copy": "Indexar “por las dudas” es una deuda silenciosa.\n\nNuevo artículo: “Índices SQL que aceleran… y los que solo ocupan disco.”\n\n[ARTICLE LINK]\n#SQL #Databases #SoftwareEngineering",
        "instagram_task": "☐ Story: título + tip de EXPLAIN + link. Guardá evidencia.",
    },
    10: {
        "blog_title": "Caché: invalidación, TTL y mentiras convenientes",
        "blog_angle": "La caché acelera lecturas, pero la invalidación mal diseñada crea bugs peores que la lentitud.",
        "blog_draft": "casos de uso → TTL vs event-driven → stampedes → patrones → CTA. 600–900 palabras.",
        "x_copy": "Hoy: “Caché: invalidación, TTL y mentiras convenientes”. Lo difícil no es cachear: es invalidar bien.[ARTICLE LINK] #Backend",
        "linkedin_copy": "“Está en caché” no es una arquitectura: es una decisión con trade-offs.\n\nArtículo: “Caché: invalidación, TTL y mentiras convenientes.”\n\n[ARTICLE LINK]\n#Backend #Performance #SoftwareEngineering",
        "instagram_task": "☐ Story con título + takeaway de invalidación + [ARTICLE LINK].",
    },
    18: {
        "blog_title": "Colas de mensajes: at-least-once y lo que implica",
        "blog_angle": "Si tu consumidor no es idempotente, “at-least-once” se convierte en “al menos un desastre”.",
        "blog_draft": "modelos de entrega → reintentos → DLQ → diseño del handler → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Colas de mensajes: at-least-once y lo que implica”. Diseñá el consumidor para duplicados.[ARTICLE LINK] #Messaging #Backend",
        "linkedin_copy": "Las colas no eliminan la complejidad: la mueven al consumidor.\n\nNuevo: “Colas de mensajes: at-least-once y lo que implica.”\n\n[ARTICLE LINK]\n#Backend #Architecture #DistributedSystems",
        "instagram_task": "☐ Story: título + tip de DLQ + link.",
    },
    19: {
        "blog_title": "Event-driven architecture sin magia: límites y ownership",
        "blog_angle": "Los eventos ayudan a desacoplar, pero sin ownership claro creás un grafo imposible de depurar.",
        "blog_draft": "cuándo usar eventos → contratos → tracing → anti-patrones → CTA. 600–900 palabras.",
        "x_copy": "Nuevo: “Event-driven architecture sin magia”. Desacoplar ≠ dejar de ser dueño del outcome.[ARTICLE LINK] #Architecture",
        "linkedin_copy": "Event-driven no es una religión: es un trade-off de acoplamiento temporal.\n\nArtículo: “Event-driven architecture sin magia: límites y ownership.”\n\n[ARTICLE LINK]\n#SoftwareArchitecture #Engineering",
        "instagram_task": "☐ Story con título + 1 anti-patrón + [ARTICLE LINK].",
    },
    20: {
        "blog_title": "Rate limiting: proteger APIs sin castigar usuarios reales",
        "blog_angle": "Un buen rate limit distingue abuso de picos legítimos y falla con mensajes accionables.",
        "blog_draft": "amenazas → token bucket/leaky → identidad → headers → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Rate limiting: proteger APIs sin castigar usuarios reales”. Límites claros, errores útiles.[ARTICLE LINK] #APIs #Security",
        "linkedin_copy": "Sin rate limiting, tu API es un recurso compartido sin reglas.\n\nNuevo: “Rate limiting: proteger APIs sin castigar usuarios reales.”\n\n[ARTICLE LINK]\n#APIs #Security #Backend",
        "instagram_task": "☐ Story: título + tip de 429 útil + link.",
    },
    21: {
        "blog_title": "SLO, SLI y error budget: hablar de confiabilidad con números",
        "blog_angle": "Sin error budget, cada incidente se discute con opiniones en lugar de con capacidad restante.",
        "blog_draft": "definiciones → elegir SLIs → error budget → decisiones de producto → CTA. 600–900 palabras.",
        "x_copy": "Hoy: “SLO, SLI y error budget”. La confiabilidad se gestiona con números, no con eslóganes.[ARTICLE LINK] #SRE",
        "linkedin_copy": "“Que no se caiga” no es un objetivo.\n\nArtículo: “SLO, SLI y error budget: hablar de confiabilidad con números.”\n\n[ARTICLE LINK]\n#SRE #Reliability #EngineeringLeadership",
        "instagram_task": "☐ Story con título + definición corta de error budget + link.",
    },
    78: {
        "blog_title": "Migraciones de base de datos sin downtime",
        "blog_angle": "Expand/contract permite cambiar el schema mientras el tráfico sigue vivo.",
        "blog_draft": "riesgos → expand/contract → backfills → rollback → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Migraciones de base de datos sin downtime”. Expand/contract antes del corte grande.[ARTICLE LINK] #Databases",
        "linkedin_copy": "Una migración “rápida” en horario pico puede costar más que una migración cuidadosa.\n\nNuevo: “Migraciones de base de datos sin downtime.”\n\n[ARTICLE LINK]\n#Databases #DevOps #SoftwareEngineering",
        "instagram_task": "☐ Story: título + tip expand/contract + link.",
    },
    79: {
        "blog_title": "Observabilidad vs monitoreo: tres pilares útiles",
        "blog_angle": "Métricas, logs y traces responden preguntas distintas; mezclarlos sin diseño genera ruido.",
        "blog_draft": "diferencias → cuándo cada pilar → correlación → costos → CTA. 600–900 palabras.",
        "x_copy": "Nuevo: “Observabilidad vs monitoreo”. Instrumentá para preguntas, no para vanidad.[ARTICLE LINK] #Observability",
        "linkedin_copy": "Más dashboards no equivalen a más claridad.\n\nArtículo: “Observabilidad vs monitoreo: tres pilares útiles.”\n\n[ARTICLE LINK]\n#Observability #SRE #Engineering",
        "instagram_task": "☐ Story con título + métricas/logs/traces + link.",
    },
    81: {
        "blog_title": "Autenticación moderna: sesiones, JWT y trade-offs",
        "blog_angle": "Elegir JWT “porque es moderno” ignora revocación, rotación y superficie de ataque.",
        "blog_draft": "sesiones vs tokens → refresh → almacenamiento → amenazas → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Autenticación moderna: sesiones, JWT y trade-offs”. No hay token mágico.[ARTICLE LINK] #Security #Backend",
        "linkedin_copy": "La autenticación es diseño de amenazas, no preferencia de librería.\n\nNuevo: “Autenticación moderna: sesiones, JWT y trade-offs.”\n\n[ARTICLE LINK]\n#Security #Backend #SoftwareEngineering",
        "instagram_task": "☐ Story: título + 1 trade-off JWT vs sesión + link.",
    },
    82: {
        "blog_title": "Autorización: RBAC, ABAC y el costo de “después lo vemos”",
        "blog_angle": "Los bugs de autorización son silenciosos hasta que alguien ve datos que no debería.",
        "blog_draft": "modelos → puntos de enforcement → pruebas → anti-patrones → CTA. 600–900 palabras.",
        "x_copy": "Hoy: “Autorización: RBAC, ABAC y el costo de ‘después lo vemos’”. Enforce cerca del dato.[ARTICLE LINK] #Security",
        "linkedin_copy": "Si la autorización vive solo en el frontend, no tenés autorización.\n\nArtículo: “Autorización: RBAC, ABAC y el costo de ‘después lo vemos’.”\n\n[ARTICLE LINK]\n#Security #Architecture #SoftwareEngineering",
        "instagram_task": "☐ Story con título + tip de enforcement server-side + link.",
    },
    83: {
        "blog_title": "Secret management: dejar de hardcodear credenciales",
        "blog_angle": "Los secretos en repos, CI logs o imágenes Docker son incidentes esperando fecha.",
        "blog_draft": "fugas comunes → vaults/KMS → rotación → least privilege → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Secret management: dejar de hardcodear credenciales”. Rotá, no solo escondé.[ARTICLE LINK] #Security #DevOps",
        "linkedin_copy": "Un secreto en git history sigue siendo un secreto comprometido.\n\nNuevo: “Secret management: dejar de hardcodear credenciales.”\n\n[ARTICLE LINK]\n#DevOps #Security #Engineering",
        "instagram_task": "☐ Story: título + tip de rotación + link.",
    },
    84: {
        "blog_title": "Testing en la pirámide: unit, integration y contract tests",
        "blog_angle": "Demasiados e2e lentos suelen esconder una pirámide invertida y feedback caro.",
        "blog_draft": "pirámide → qué va en cada capa → contract tests → velocidad → CTA. 600–900 palabras.",
        "x_copy": "Nuevo: “Testing en la pirámide”. Feedback rápido > teatro de cobertura.[ARTICLE LINK] #Testing #Engineering",
        "linkedin_copy": "La cobertura alta con tests frágiles no es calidad: es costo operativo.\n\nArtículo: “Testing en la pirámide: unit, integration y contract tests.”\n\n[ARTICLE LINK]\n#Testing #SoftwareEngineering #Quality",
        "instagram_task": "☐ Story con título + tip de contract tests + link.",
    },
    91: {
        "blog_title": "Throughput vs latencia: optimizar la métrica correcta",
        "blog_angle": "Mejorar p99 suele exigir decisiones distintas a maximizar requests por segundo.",
        "blog_draft": "definiciones → cuellos de botella → colas → profiling → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Throughput vs latencia”. Primero definí qué duele al usuario.[ARTICLE LINK] #Performance",
        "linkedin_copy": "“Más rápido” es ambiguo hasta que elegís la métrica.\n\nNuevo: “Throughput vs latencia: optimizar la métrica correcta.”\n\n[ARTICLE LINK]\n#Performance #Backend #SoftwareEngineering",
        "instagram_task": "☐ Story: título + diferencia p50/p99 + link.",
    },
    92: {
        "blog_title": "Backpressure: qué hacer cuando el productor es más rápido",
        "blog_angle": "Sin backpressure, bufferizar infinito solo posterga el outage.",
        "blog_draft": "síntomas → load shedding → colas acotadas → UX degradada → CTA. 600–900 palabras.",
        "x_copy": "Hoy: “Backpressure”. Si no podés procesar, no fingas que sí.[ARTICLE LINK] #DistributedSystems",
        "linkedin_copy": "Los sistemas saludables saben decir “más despacio”.\n\nArtículo: “Backpressure: qué hacer cuando el productor es más rápido.”\n\n[ARTICLE LINK]\n#DistributedSystems #Backend #Reliability",
        "instagram_task": "☐ Story con título + idea de load shedding + link.",
    },
    93: {
        "blog_title": "Dead letter queues: recuperar fallos sin perder la señal",
        "blog_angle": "Una DLQ sin proceso de triage es solo un cementerio de mensajes.",
        "blog_draft": "cuándo usar DLQ → alertas → replay → ownership → checklist. 600–900 palabras.",
        "x_copy": "Artículo: “Dead letter queues”. Guardar el fallo no alcanza: hay que operarlo.[ARTICLE LINK] #Messaging #SRE",
        "linkedin_copy": "Si nadie mira la DLQ, el sistema está fallando en silencio.\n\nNuevo: “Dead letter queues: recuperar fallos sin perder la señal.”\n\n[ARTICLE LINK]\n#Messaging #SRE #Backend",
        "instagram_task": "☐ Story: título + tip de replay controlado + link.",
    },
    94: {
        "blog_title": "Canary deployments: reducir el radio de explosión",
        "blog_angle": "Un canary bien instrumentado detecta regresiones antes de que lleguen al 100%.",
        "blog_draft": "estrategia → métricas de promoción → abort criteria → automatización → CTA. 600–900 palabras.",
        "x_copy": "Nuevo: “Canary deployments”. Promové con evidencia, no con esperanza.[ARTICLE LINK] #CI_CD #DevOps",
        "linkedin_copy": "El deploy progresivo solo funciona si medís lo que importa.\n\nArtículo: “Canary deployments: reducir el radio de explosión.”\n\n[ARTICLE LINK]\n#DevOps #SRE #SoftwareEngineering",
        "instagram_task": "☐ Story con título + criterio de abort + link.",
    },
    95: {
        "blog_title": "Blue/green y rollback: ensayar la salida de emergencia",
        "blog_angle": "Un rollback que nunca se practicó no es un plan: es una ilusión.",
        "blog_draft": "blue/green → compatibilidad de datos → drills → checklist → CTA. 600–900 palabras.",
        "x_copy": "Artículo: “Blue/green y rollback”. Practicá el camino de vuelta.[ARTICLE LINK] #DevOps #Reliability",
        "linkedin_copy": "La pregunta útil no es “¿podemos desplegar?” sino “¿podemos volver atrás?”.\n\nNuevo: “Blue/green y rollback: ensayar la salida de emergencia.”\n\n[ARTICLE LINK]\n#DevOps #Reliability #Engineering",
        "instagram_task": "☐ Story: título + tip de drill de rollback + link.",
    },
    96: {
        "blog_title": "Infrastructure as Code: repetible, revisable, reversible",
        "blog_angle": "Si la infra solo vive en la consola cloud, el bus factor es 1 y el audit trail es anecdótico.",
        "blog_draft": "por qué IaC → módulos → drift → reviews → checklist. 600–900 palabras.",
        "x_copy": "Hoy: “Infrastructure as Code”. La infra también merece review y rollback.[ARTICLE LINK] #IaC #DevOps",
        "linkedin_copy": "ClickOps escala hasta el primer incidente de las 3 AM.\n\nArtículo: “Infrastructure as Code: repetible, revisable, reversible.”\n\n[ARTICLE LINK]\n#DevOps #Cloud #PlatformEngineering",
        "instagram_task": "☐ Story con título + beneficio de drift detection + link.",
    },
    97: {
        "blog_title": "FinOps práctico para equipos de ingeniería",
        "blog_angle": "El costo cloud es una señal de diseño: idle resources, overprovision y data egress cuentan.",
        "blog_draft": "visibilidad → tags → derechosizing → trade-offs → CTA. 600–900 palabras.",
        "x_copy": "Artículo: “FinOps práctico”. El presupuesto también es arquitectura.[ARTICLE LINK] #Cloud #FinOps",
        "linkedin_copy": "Optimizar costo sin romper SLOs es ingeniería, no contabilidad.\n\nNuevo: “FinOps práctico para equipos de ingeniería.”\n\n[ARTICLE LINK]\n#Cloud #PlatformEngineering #SoftwareEngineering",
        "instagram_task": "☐ Story: título + tip de tagging + link.",
    },
    98: {
        "blog_title": "Diseño de esquemas para high write throughput",
        "blog_angle": "Particionado, claves calientes y escrituras amplificadas definen si el sistema aguanta el pico.",
        "blog_draft": "hot keys → particiones → batching → medición → checklist. 600–900 palabras.",
        "x_copy": "Nuevo: “Diseño de esquemas para high write throughput”. Cuidado con las claves calientes.[ARTICLE LINK] #Databases",
        "linkedin_copy": "El cuello de botella de escritura casi nunca se arregla “poniendo más RAM” a ciegas.\n\nArtículo: “Diseño de esquemas para high write throughput.”\n\n[ARTICLE LINK]\n#Databases #Performance #Backend",
        "instagram_task": "☐ Story con título + idea de hot key + link.",
    },
    99: {
        "blog_title": "Postmortems sin culpa: convertir incidentes en aprendizaje",
        "blog_angle": "Un buen postmortem busca condiciones del sistema, no villanos, y deja acciones verificables.",
        "blog_draft": "timeline → factores contribuyentes → acciones → seguimiento → CTA. 600–900 palabras.",
        "x_copy": "Artículo: “Postmortems sin culpa”. Aprendé del sistema, no castigues al síntoma.[ARTICLE LINK] #SRE #Leadership",
        "linkedin_copy": "La culpa individual suele esconder deuda de proceso, tooling y ownership.\n\nNuevo: “Postmortems sin culpa: convertir incidentes en aprendizaje.”\n\n[ARTICLE LINK]\n#SRE #EngineeringLeadership #Reliability",
        "instagram_task": "☐ Story: título + tip de action items medibles + link.",
    },
    100: {
        "blog_title": "Arquitectura evolutiva: cambiar sin reescribir todo",
        "blog_angle": "Los sistemas duraderos se adaptan por límites claros, no por big-bang rewrites.",
        "blog_draft": "estrangulador → módulos → métricas de cambio → riesgos → CTA. 600–900 palabras.",
        "x_copy": "Cierre técnico: “Arquitectura evolutiva: cambiar sin reescribir todo”. Evolucioná por límites.[ARTICLE LINK] #Architecture",
        "linkedin_copy": "Reescribir desde cero suele reiniciar los mismos problemas con nombres nuevos.\n\nArtículo: “Arquitectura evolutiva: cambiar sin reescribir todo.”\n\n[ARTICLE LINK]\n#SoftwareArchitecture #Engineering #TechLeadership",
        "instagram_task": "☐ Story con título + tip del strangler pattern + [ARTICLE LINK]. Guardá evidencia.",
    },
}


def main() -> None:
    days = json.loads(DAYS.read_text(encoding="utf-8"))
    changed = []
    for d in days:
        n = int(d["day"])
        if n not in REPLACEMENTS:
            continue
        pack = REPLACEMENTS[n]
        old = d.get("blog_title")
        for k, v in pack.items():
            d[k] = v
        changed.append((n, old, pack["blog_title"]))

    DAYS.write_text(json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    # embed days.php
    payload = json.dumps(days, ensure_ascii=False, indent=2)
    out = ROOT / "data" / "days.php"
    out.write_text(
        "<?php\ndeclare(strict_types=1);\n\n"
        "/** Plan 100 días en español — artículos técnicos. */\n"
        "return json_decode(<<<'JSON'\n"
        + payload
        + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
        encoding="utf-8",
    )

    print(f"updated {len(changed)} days")
    for n, old, new in changed:
        print(f"  D{n}: {old} -> {new}")
    print(f"wrote {out}")


if __name__ == "__main__":
    main()
