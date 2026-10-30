"""PR44: navegador real, datos ficticios y servidor con identidad verificada.

DHACER_RED_ENV apunta al manifiesto privado creado fuera del repositorio.
No utiliza el servidor operativo, perfiles existentes ni credenciales reales.
"""

import base64
import hashlib
import hmac
import json
import os
from pathlib import Path
import re
import secrets
import subprocess
from urllib.request import urlopen

import pytest
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.support.ui import Select, WebDriverWait

BASE = "http://127.0.0.1:8026"
ROOT = Path(__file__).resolve().parents[2]


class Escenario:
    def __init__(self):
        self.lab = Path(os.environ["DHACER_RED_ENV"])
        self.manifest = json.loads((self.lab / "manifiesto.json").read_text("utf-8-sig"))
        self.private = json.loads((self.lab / "credenciales-privadas.json").read_text("utf-8-sig"))
        assert Path(self.manifest["checkout"]).resolve() == ROOT
        assert self.manifest["base_visual"].startswith("dhacer_eco_limp_test_visual_")
        nonce = secrets.token_hex(20)
        with urlopen(f"{BASE}/_pr44/identidad?nonce={nonce}", timeout=10) as response:
            identity = json.load(response)
        expected = hmac.new(self.private["visual"].encode(), f"{nonce}|{self.manifest['uuid']}|{self.manifest['base_visual']}".encode(), hashlib.sha256).hexdigest()
        assert identity["uuid"] == self.manifest["uuid"]
        assert identity["base"] == self.manifest["base_visual"]
        assert hmac.compare_digest(identity["firma"], expected), "Identidad aislada incorrecta"
        options = webdriver.ChromeOptions()
        options.binary_location = os.environ.get("DHACER_CHROME", r"C:\Program Files\Google\Chrome\Application\chrome.exe")
        for argument in ["--headless=new", "--window-size=1366,1000", "--no-first-run", "--disable-background-networking", "--disable-extensions"]:
            options.add_argument(argument)
        options.set_capability("goog:loggingPrefs", {"browser": "ALL"})
        options.add_experimental_option("prefs", {"credentials_enable_service": False, "profile.password_manager_enabled": False})
        self.driver = webdriver.Chrome(service=Service(os.environ["DHACER_CHROMEDRIVER"]), options=options)
        self.wait = WebDriverWait(self.driver, 15)
        self.evidence = self.lab / self.manifest.get("evidencia_visual", "evidencia")
        saved_ids = self.evidence / "escenario-ids.json"
        self.ids = json.loads(saved_ids.read_text("utf-8")) if saved_ids.exists() else {}

    def get(self, path):
        self.driver.get(BASE + path)
        self.wait.until(lambda d: d.execute_script("return document.readyState") == "complete")
        assert "Server Error" not in self.driver.title
        assert "SQLSTATE[" not in self.driver.find_element(By.TAG_NAME, "body").text

    def find(self, css, scope=None):
        return (scope or self.driver).find_element(By.CSS_SELECTOR, css)

    def field(self, name, value, scope=None):
        element = self.find(f'[name="{name}"]', scope)
        element.send_keys(Keys.CONTROL, "a")
        element.send_keys(str(value))
        return element

    def click(self, element):
        self.driver.execute_script("arguments[0].scrollIntoView({block:'center'})", element)
        element.click()

    def button(self, text, scope=None):
        return (scope or self.driver).find_element(By.XPATH, f".//button[normalize-space(.)='{text}']")

    def submit(self, form, button=None):
        previous = self.find("body")
        submit = button or self.find('button[type="submit"].btn-primary,button:not([type]).btn-primary', form)
        confirm = "confirm(" in (submit.get_attribute("onclick") or "")
        self.click(submit)
        if confirm:
            self.wait.until(EC.alert_is_present())
            self.driver.switch_to.alert.accept()
        self.wait.until(EC.staleness_of(previous))
        self.wait.until(lambda d: d.execute_script("return document.readyState") == "complete")
        assert "SQLSTATE[" not in self.find("body").text
        assert "Server Error" not in self.driver.title

    def login(self, role):
        self.driver.delete_all_cookies()
        self.get("/login")
        self.field("email", role + "@pr44.test")
        self.field("password", self.private["clave_navegador"])
        form = self.find("form")
        self.click(self.find('button[type="submit"]', form))
        self.wait.until(lambda d: "/login" not in d.current_url)
        assert "Cambiar contraseña" not in self.driver.title

    def snapshot(self, name):
        result = subprocess.run(["php", "tests/Selenium/preparar_redisenio.php", "evidencia"], cwd=ROOT, capture_output=True, text=True, encoding="utf-8", check=True)
        data = json.loads(result.stdout)
        (self.evidence / f"{name}.json").write_text(json.dumps(data, ensure_ascii=False, indent=2), "utf-8")
        return data

    def shot(self, name):
        self.driver.save_screenshot(str(self.evidence / f"{name}.png"))

    def add_sku(self, product, aroma, amount):
        products = self.find("[data-productos]")
        self.wait.until(lambda d: any(o.text == product for o in Select(products).options))
        Select(products).select_by_visible_text(product)
        prefix = {"Limpiatodo ficticio": "LIM-", "Quitagrasa ficticio": "QGR-", "Limpiavidrios ficticio": "VID-"}[product]
        self.wait.until(lambda d: any(prefix in o.text for o in Select(self.find("[data-skus]")).options))
        if aroma:
            Select(self.find("[data-variantes]")).select_by_visible_text(aroma)
        else:
            assert not self.find("[data-campo-variante]").is_displayed()
        sku_select = Select(self.find("[data-skus]"))
        sku_select.select_by_index(1)
        sku = sku_select.first_selected_option.get_attribute("value")
        amount_field = self.find("[data-cantidad-nueva]")
        amount_field.send_keys(Keys.CONTROL, "a")
        amount_field.send_keys(str(amount))
        self.click(self.find("[data-agregar-linea]"))
        return sku

    def order(self, product, aroma, amount):
        self.get("/pedidos/nuevo")
        self.wait.until(lambda d: len(Select(self.find("[data-clientes]")).options) > 1)
        Select(self.find("[data-clientes]")).select_by_visible_text("Cliente ficticio PR44")
        return self.add_sku(product, aroma, amount)

    def save_order(self):
        self.submit(self.find("form[data-pedido-unidades]"))
        assert re.search(r"/pedidos/\d+$", self.driver.current_url), self.find("body").text[-1500:]
        return int(self.driver.current_url.rsplit("/", 1)[1])

    def confirm_order(self, order):
        self.get(f"/pedidos/{order}")
        form = self.find(f'form[action$="/pedidos/{order}/procesar"]')
        self.submit(form)
        assert "Confirmado" in self.find("body").text

    def prepare(self, order, amount_by_sku):
        for sku, amount in amount_by_sku.items():
            self.get(f"/cumplimiento/{order}")
            articles = self.driver.find_elements(By.CSS_SELECTOR, "article.cumplimiento-tarjeta")
            article = next(a for a in articles if f"· {sku}" in a.find_element(By.TAG_NAME, "h3").text)
            form = article.find_element(By.CSS_SELECTOR, 'form[action$="/preparacion-real"]')
            self.field("cantidad", amount, form)
            self.field("motivo", "Unidades ficticias físicamente verificadas por el personal.", form)
            self.submit(form)
            assert "Preparación física e incidencia registradas" in self.find("body").text

    def dispatch(self, order, package_index=None, loose=None):
        self.get(f"/cumplimiento/{order}")
        form = self.find("form[data-despacho-unidades]")
        if package_index is not None:
            self.click(form.find_elements(By.CSS_SELECTOR, "[data-paquete-salida]")[package_index])
        for sku_id, amount in (loose or {}).items():
            element = self.find(f'[data-salida-suelta][data-sku="{sku_id}"]', form)
            element.send_keys(Keys.CONTROL, "a")
            element.send_keys(str(amount))
        self.field("motivo", "Salida física ficticia verificada con paquetes y unidades sueltas.", form)
        self.submit(form)
        assert "Despacho físico registrado una sola vez" in self.find("body").text


@pytest.fixture(scope="module")
def scenario():
    context = Escenario()
    try:
        yield context
    finally:
        context.shot("ultima-pantalla-selenium")
        context.driver.quit()


def test_01_venta_53_unidades_agrupaciones_copia_y_precio(scenario):
    s = scenario
    s.login("operaciones")
    s.snapshot("antes-ventas")
    sku_ids = {}
    sku_ids["Lavanda"] = s.order("Limpiatodo ficticio", "Lavanda", 5)
    for aroma, amount in [("Frutos", 24), ("BB", 8), ("Manzana", 8), ("Limón", 8)]:
        sku_ids[aroma] = s.add_sku("Limpiatodo ficticio", aroma, amount)
    s.click(s.find("[data-empaquetar]"))
    s.click(s.find("[data-agregar-grupo]"))
    def capacity(value):
        element = s.find("[data-grupos] .unidades-grupo > label input")
        element.send_keys(Keys.CONTROL, "a")
        element.send_keys(str(value))
    def component(aroma, amount):
        s.click(s.button("Añadir variante al paquete"))
        elements = s.driver.find_elements(By.CSS_SELECTOR, '[aria-label="SKU del paquete"]')
        Select(elements[-1]).select_by_value(sku_ids[aroma])
        number = s.driver.find_elements(By.CSS_SELECTOR, '[aria-label="Unidades dentro del paquete"]')[-1]
        number.send_keys(Keys.CONTROL, "a")
        number.send_keys(str(amount))
    capacity(24)
    component("Frutos", 24)
    s.click(s.button("Copiar este paquete"))
    assert "Paquete 2" in s.find("[data-grupos]").text
    s.click(s.button("Quitar componente"))
    for aroma in ["BB", "Manzana", "Limón"]:
        component(aroma, 8)
    s.shot("01-editor-dos-paquetes-escritorio")
    summary = s.find("[data-resumen]").text
    assert "53 unidades físicas" in summary and "5 sueltas" in summary and "48 agrupadas" in summary and "106.00" in summary
    s.ids["principal"] = s.save_order()
    s.ids["skus"] = sku_ids
    assert "106.00" in s.find("body").text
    assert "24 unidades de Limpiatodo ficticio · Frutos" in s.find("body").text
    s.confirm_order(s.ids["principal"])
    s.order("Limpiavidrios ficticio", None, 7)
    s.ids["simple"] = s.save_order()
    assert "21.00" in s.find("body").text
    s.order("Quitagrasa ficticio", None, 54)
    s.ids["quitagrasa"] = s.save_order()
    s.confirm_order(s.ids["quitagrasa"])
    (s.evidence / "escenario-ids.json").write_text(json.dumps(s.ids), "utf-8")
    after = s.snapshot("despues-ventas-sin-movimientos")
    assert after["movimientos"] == 0
    assert after["inventarios"]["QGR-1"] == 0


def test_02_preparacion_despachos_parciales_sin_duplicar_sku(scenario):
    s = scenario
    s.login("reparto")
    order = s.ids["principal"]
    s.get("/cumplimiento/lista-preparacion")
    body = s.find("body").text
    for aroma in ["Lavanda", "Frutos", "BB", "Manzana", "Limón"]:
        assert aroma in body
    s.shot("02-lista-general-escritorio")
    s.prepare(order, {"LIM-1": 5, "LIM-2": 24, "LIM-3": 8, "LIM-4": 8, "LIM-5": 8})
    s.dispatch(order, package_index=0, loose={s.ids["skus"]["Lavanda"]: 5})
    state = s.snapshot("salida-parcial-29")
    assert state["inventarios"]["LIM-1"] == 95 and state["inventarios"]["LIM-2"] == 76
    assert state["inventarios"]["LIM-3"] == 100
    assert next(p for p in state["pedidos"] if p["id"] == order)["estado"] == "despacho_parcial"
    s.dispatch(order, package_index=0)
    state = s.snapshot("salida-final-53")
    assert [state["inventarios"][f"LIM-{i}"] for i in range(1, 6)] == [95, 76, 92, 92, 92]
    assert state["movimientos"] == 5
    s.shot("03-distribucion-despachos")


def test_03_rechazo_recepcion_y_aprobacion_separados(scenario):
    s = scenario
    order = s.ids["principal"]
    s.get(f"/cumplimiento/{order}")
    form = s.find(f'form[action$="/cumplimiento/{order}/resultado-entrega"]')
    s.click(form.find_element(By.XPATH, "..//summary"))
    for fieldset in form.find_elements(By.TAG_NAME, "fieldset"):
        text = fieldset.find_element(By.TAG_NAME, "legend").text
        dispatched = int(re.search(r"pendientes (\d+)", text).group(1))
        rejected = 2 if "LIM-3" in text else 0
        accepted = fieldset.find_element(By.CSS_SELECTOR, '[name$="[aceptadas]"]')
        accepted.send_keys(Keys.CONTROL, "a")
        accepted.send_keys(str(dispatched - rejected))
        if rejected:
            field = fieldset.find_element(By.CSS_SELECTOR, '[name$="[rechazadas]"]')
            field.send_keys(Keys.CONTROL, "a")
            field.send_keys("2")
            fieldset.find_element(By.TAG_NAME, "textarea").send_keys("El cliente informa que no solicitó dos unidades de este aroma.")
    s.submit(form)
    assert s.snapshot("rechazo-sin-entrada")["inventarios"]["LIM-3"] == 92
    form = s.find(f'form[action$="/cumplimiento/{order}/retornos"]')
    s.click(form.find_element(By.XPATH, "..//summary"))
    fieldset = next(f for f in form.find_elements(By.TAG_NAME, "fieldset") if "LIM-3" in f.find_element(By.TAG_NAME, "legend").text)
    for field in ["aptas", "danadas"]:
        element = fieldset.find_element(By.CSS_SELECTOR, f'[name$="[{field}]"]')
        element.send_keys(Keys.CONTROL, "a")
        element.send_keys("1")
    s.field("observaciones", "Una unidad apta y otra dañada verificadas físicamente en almacén.", form)
    s.submit(form)
    assert s.snapshot("recepcion-sin-aprobacion")["inventarios"]["LIM-3"] == 92
    assert not s.driver.find_elements(By.CSS_SELECTOR, 'form[action$="/aprobar-retorno"]')
    assert "Rectificación administrativa autorizada" not in s.find("body").text
    s.shot("04-retorno-reparto-sin-aprobacion")
    s.login("operaciones")
    s.get(f"/cumplimiento/{order}")
    form = s.find(f'form[action$="/cumplimiento/{order}/aprobar-retorno"]')
    s.click(form.find_element(By.XPATH, "..//summary"))
    s.field("cantidad", 1, form)
    s.field("motivo", "Se verificó físicamente una unidad apta; la dañada no se reincorpora.", form)
    s.submit(form)
    state = s.snapshot("retorno-aprobado-una-unidad")
    assert state["inventarios"]["LIM-3"] == 93
    assert sum(state["inventarios"][f"LIM-{i}"] for i in range(1, 6)) == 448
    assert all(str(value) == "200.000" for key, value in state["materiales"].items() if key != "MANGA")
    s.shot("05-entrega-parcial-y-retorno-aprobado")


def test_04_envasado_integrado_reduccion_54_a_32_y_despacho(scenario):
    s = scenario
    order = s.ids["quitagrasa"]
    s.login("reparto")
    s.get(f"/cumplimiento/{order}")
    s.click(s.find("[data-registrar-envasado]"))
    dialog = s.find("#envasado-integrado")
    Select(s.find('[name="configuracion_envasado_id"]', dialog)).select_by_visible_text("Configuración ficticia PR44")
    s.field("cantidad", 32, dialog)
    Select(s.find('[name="pedido_id"]', dialog)).select_by_value(str(order))
    s.click(s.button("Consultar consumos antes de confirmar", dialog))
    s.wait.until(lambda d: len(d.find_elements(By.CSS_SELECTOR, '[data-vista-previa-envasado] form')) == 1)
    s.shot("06-confirmacion-envasado-integrado")
    form = s.find("[data-vista-previa-envasado] form")
    s.click(s.find('input[type="checkbox"]', form))
    s.submit(form)
    state = s.snapshot("envasado-32")
    assert state["inventarios"]["QGR-1"] == 32
    assert [state["materiales"][key] for key in ["ENVASE", "ETIQUETA", "CONTRATAPA"]] == ["168.000"] * 3
    assert state["materiales"]["MANGA"] == "100.000"
    s.prepare(order, {"QGR-1": 32})
    s.login("operaciones")
    s.get(f"/pedidos/{order}/editar")
    s.field("detalles[0][cantidad]", 32)
    s.field("motivo", "Se reduce la cantidad vigente de 54 a 32 por falta de insumos químicos, sin prometer reposición.")
    s.save_order()
    assert "192.00" in s.find("body").text
    assert "54" in s.driver.page_source and "32" in s.driver.page_source
    state = s.snapshot("reduccion-comercial-sin-modificar-produccion")
    assert state["inventarios"]["QGR-1"] == 32
    assert state["materiales"]["ENVASE"] == "168.000"
    s.login("reparto")
    s.get(f"/cumplimiento/{order}")
    form = s.find("form[data-despacho-unidades]")
    sku_id = s.find("[data-salida-suelta]", form).get_attribute("data-sku")
    s.dispatch(order, loose={sku_id: 32})
    assert s.snapshot("quitagrasa-despachado")["inventarios"]["QGR-1"] == 0


def test_05_moviles_impresion_y_pedidos_historicos(scenario):
    s = scenario
    s.login("operaciones")
    for width in [1366, 360, 390, 768, 1024]:
        s.driver.set_window_size(width, 950)
        s.driver.execute_cdp_cmd("Emulation.setDeviceMetricsOverride", {"width": width, "height": 950, "deviceScaleFactor": 1, "mobile": width < 650})
        assert s.driver.execute_script("return innerWidth") == width, "La captura debe usar el ancho real solicitado"
        for label, path in [("pedido", f"/pedidos/{s.ids['principal']}"), ("editor", f"/pedidos/{s.ids['quitagrasa']}/editar"), ("preparacion", "/cumplimiento/lista-preparacion"), ("retornos", f"/cumplimiento/{s.ids['principal']}")]:
            s.get(path)
            assert s.driver.execute_script("return innerWidth") == width, f"Ancho efectivo incorrecto en {label}"
            assert s.driver.execute_script("return document.documentElement.scrollWidth") <= width + 1, f"Desborde {label} a {width}px"
            if width in [1366, 360, 390]:
                s.shot(f"{label}-{width}px")
    s.driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
    s.driver.set_window_size(1366, 1000)
    s.get("/pedidos/1")
    assert "48.00" in s.find("body").text
    s.get("/pedidos/1/editar")
    assert "histórico" in s.find("body").text.lower()
    s.shot("07-editor-historico-sin-conversion")
    state = s.snapshot("final-integral")
    historical = next(p for p in state["pedidos"] if p["id"] == 1)
    assert historical["total"] == "48.00" and historical["modelo_comercial"] is None
    s.get(f"/pedidos/imprimir-dos?pedido_1={s.ids['principal']}&pedido_2={s.ids['simple']}")
    assert len(s.driver.find_elements(By.CSS_SELECTOR, ".nota")) == 2
    assert len(s.driver.find_elements(By.CSS_SELECTOR, '[aria-label="Anexo de agrupaciones logísticas"]')) == 1
    pdf = s.driver.execute_cdp_cmd("Page.printToPDF", {"landscape": True, "preferCSSPageSize": True, "printBackground": True, "displayHeaderFooter": False})
    (s.evidence / "notas-y-agrupaciones-pr44.pdf").write_bytes(base64.b64decode(pdf["data"]))
    severe = [r for r in s.driver.get_log("browser") if r["level"] == "SEVERE" and "favicon.ico" not in r["message"]]
    (s.evidence / "consola.json").write_text(json.dumps(severe, ensure_ascii=False, indent=2), "utf-8")
    assert not severe


def test_06_modal_y_agrupaciones_en_360_390_y_trazabilidad_visible(scenario):
    s = scenario
    before = s.snapshot("antes-revision-modal-movil")
    s.login("operaciones")
    s.order("Quitagrasa ficticio", None, 1)
    visual = s.save_order()
    s.confirm_order(visual)
    s.ids["solo_consulta_modal"] = visual
    (s.evidence / "escenario-ids.json").write_text(json.dumps(s.ids), "utf-8")
    s.login("reparto")
    for width in [360, 390]:
        s.driver.execute_cdp_cmd("Emulation.setDeviceMetricsOverride", {"width": width, "height": 950, "deviceScaleFactor": 1, "mobile": True})
        s.get("/cumplimiento/lista-preparacion")
        assert s.driver.execute_script("return innerWidth") == width
        s.click(s.find('[data-registrar-envasado][data-sku="QGR-1"]'))
        dialog = s.find("#envasado-integrado")
        Select(s.find('[name="configuracion_envasado_id"]', dialog)).select_by_visible_text("Configuración ficticia PR44")
        s.field("cantidad", 1, dialog)
        s.click(s.button("Consultar consumos antes de confirmar", dialog))
        s.wait.until(lambda d: len(d.find_elements(By.CSS_SELECTOR, '[data-vista-previa-envasado] form')) == 1)
        confirmation = s.find("[data-confirmacion-envasado]")
        s.driver.execute_script("arguments[0].scrollIntoView({block:'start'})", confirmation)
        assert s.driver.execute_script("return document.documentElement.scrollWidth") <= width + 1
        bounds = dialog.rect
        assert bounds["x"] >= 0 and bounds["x"] + bounds["width"] <= width + 1
        s.shot(f"modal-consumos-{width}px")
        s.driver.switch_to.active_element.send_keys(Keys.ESCAPE)
        s.wait.until(lambda d: not dialog.is_displayed())
    after = s.snapshot("despues-revision-modal-sin-confirmar")
    assert after["inventarios"] == before["inventarios"]
    assert after["materiales"] == before["materiales"]
    assert after["movimientos"] == before["movimientos"]
    s.login("operaciones")
    for width in [360, 390]:
        s.driver.execute_cdp_cmd("Emulation.setDeviceMetricsOverride", {"width": width, "height": 950, "deviceScaleFactor": 1, "mobile": True})
        s.get(f"/pedidos/{s.ids['principal']}/editar")
        group = s.find("[data-grupos]")
        s.driver.execute_script("arguments[0].scrollIntoView({block:'start'})", group)
        assert s.driver.execute_script("return innerWidth") == width
        assert s.driver.execute_script("return document.documentElement.scrollWidth") <= width + 1
        assert "Paquete ya despachado" in group.text
        s.shot(f"agrupaciones-editor-{width}px")
    s.driver.execute_cdp_cmd("Emulation.clearDeviceMetricsOverride", {})
    s.driver.set_window_size(1366, 1000)
    s.get(f"/cumplimiento/{s.ids['principal']}")
    heading = s.driver.find_element(By.XPATH, "//h3[normalize-space(.)='Salidas físicas registradas']")
    section = heading.find_element(By.XPATH, "..")
    for details in section.find_elements(By.CSS_SELECTOR, "details"):
        if not details.get_attribute("open"):
            s.click(details.find_element(By.TAG_NAME, "summary"))
    s.driver.execute_script("arguments[0].scrollIntoView({block:'start'})", heading)
    s.shot("08-salidas-con-distribucion-expandida")
    s.get(f"/cumplimiento/{s.ids['quitagrasa']}")
    heading = s.driver.find_element(By.XPATH, "//h3[normalize-space(.)='Cambios respecto a la solicitud original']")
    s.driver.execute_script("arguments[0].scrollIntoView({block:'start'})", heading)
    s.shot("09-ajuste-54-a-32-auditado")
