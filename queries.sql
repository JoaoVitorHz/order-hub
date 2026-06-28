-- =============================================================================
-- Order Hub - Advanced SQL Queries
-- =============================================================================

-- =============================================================================
-- Query A — Ranking de afiliados por receita
--
-- Objetivo: Listar os top 10 afiliados por receita líquida, considerando apenas
-- pedidos com status 'approved' ou 'refunded'.
--
-- Estratégia: JOIN entre affiliates, orders e order_items para calcular receita
-- bruta e reembolsos. Window function RANK() OVER(ORDER BY net_revenue DESC)
-- para adicionar coluna de ranking sem subquery adicional.
-- =============================================================================
WITH affiliate_revenue AS (
    SELECT
        a.id AS affiliate_id,
        a.name AS affiliate_name,
        COUNT(DISTINCT o.id) AS total_orders,
        SUM(oi.quantity * oi.price) AS gross_revenue,
        SUM(
            CASE WHEN o.status = 'refunded'
                 THEN oi.quantity * oi.price
                 ELSE 0
            END
        ) AS refunded_amount
    FROM affiliates a
    INNER JOIN orders o ON o.affiliate_id = a.id AND o.deleted_at IS NULL
    INNER JOIN order_items oi ON oi.order_id = o.id
    WHERE o.status IN ('approved', 'refunded')
    GROUP BY a.id, a.name
)
SELECT
    RANK() OVER (ORDER BY (gross_revenue - refunded_amount) DESC) AS ranking,
    affiliate_id,
    affiliate_name,
    total_orders,
    ROUND(gross_revenue, 2) AS gross_revenue,
    ROUND(refunded_amount, 2) AS refunded_amount,
    ROUND(gross_revenue - refunded_amount, 2) AS net_revenue
FROM affiliate_revenue
ORDER BY net_revenue DESC
LIMIT 10;


-- =============================================================================
-- Query B — Análise de cohort simplificada (últimos 6 meses)
--
-- Objetivo: Para cada mês dos últimos 6 meses, mostrar total de pedidos novos,
-- aprovados, cancelados e taxa de aprovação.
--
-- Estratégia: CTE de datas para garantir que meses sem pedidos apareçam com
-- valores zero. LEFT JOIN com os pedidos agrupados por mês. Evita subqueries
-- aninhadas usando CTEs encadeadas.
-- =============================================================================
WITH RECURSIVE month_series AS (
    SELECT DATE_FORMAT(CURRENT_DATE - INTERVAL 5 MONTH, '%Y-%m-01') AS month_start
    UNION ALL
    SELECT DATE_FORMAT(month_start + INTERVAL 1 MONTH, '%Y-%m-01')
    FROM month_series
    WHERE month_start < DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
),
order_counts AS (
    SELECT
        DATE_FORMAT(created_at, '%Y-%m-01') AS order_month,
        COUNT(*) AS total_orders,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_orders,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders
    FROM orders
    WHERE deleted_at IS NULL
      AND created_at >= DATE_FORMAT(CURRENT_DATE - INTERVAL 5 MONTH, '%Y-%m-01')
    GROUP BY DATE_FORMAT(created_at, '%Y-%m-01')
)
SELECT
    DATE_FORMAT(ms.month_start, '%Y-%m') AS month,
    COALESCE(oc.total_orders, 0) AS total_orders,
    COALESCE(oc.approved_orders, 0) AS approved_orders,
    COALESCE(oc.cancelled_orders, 0) AS cancelled_orders,
    CASE
        WHEN COALESCE(oc.total_orders, 0) = 0 THEN 0
        ELSE ROUND(COALESCE(oc.approved_orders, 0) / oc.total_orders * 100, 2)
    END AS approval_rate_pct
FROM month_series ms
LEFT JOIN order_counts oc ON oc.order_month = ms.month_start
ORDER BY ms.month_start;


-- =============================================================================
-- Query C — Detecção de pedidos duplicados
--
-- Objetivo: Identificar pedidos suspeitos de duplicidade: mesmo affiliate_id,
-- mesmo valor total (SUM(quantity * price)) e criados no mesmo dia.
--
-- Estratégia: CTE calcula o total de cada pedido. Segundo CTE agrupa por
-- (affiliate_id, total_value, date) e filtra grupos com mais de 1 pedido.
-- JOIN devolve os IDs dos pedidos envolvidos e agrupa como lista via GROUP_CONCAT.
-- =============================================================================
WITH order_totals AS (
    SELECT
        o.id AS order_id,
        o.affiliate_id,
        DATE(o.created_at) AS order_date,
        ROUND(SUM(oi.quantity * oi.price), 2) AS total_value
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    WHERE o.deleted_at IS NULL
    GROUP BY o.id, o.affiliate_id, DATE(o.created_at)
),
duplicate_groups AS (
    SELECT
        affiliate_id,
        order_date,
        total_value,
        COUNT(*) AS duplicate_count
    FROM order_totals
    GROUP BY affiliate_id, order_date, total_value
    HAVING COUNT(*) > 1
)
SELECT
    dg.affiliate_id,
    a.name AS affiliate_name,
    dg.order_date,
    dg.total_value AS duplicated_value,
    dg.duplicate_count,
    GROUP_CONCAT(ot.order_id ORDER BY ot.order_id SEPARATOR ', ') AS order_ids
FROM duplicate_groups dg
INNER JOIN order_totals ot
    ON ot.affiliate_id = dg.affiliate_id
    AND ot.order_date = dg.order_date
    AND ot.total_value = dg.total_value
INNER JOIN affiliates a ON a.id = dg.affiliate_id
GROUP BY dg.affiliate_id, a.name, dg.order_date, dg.total_value, dg.duplicate_count
ORDER BY dg.duplicate_count DESC, dg.total_value DESC;


-- =============================================================================
-- Query D — Produto mais vendido por afiliado
--
-- Objetivo: Para cada afiliado, retornar o produto que mais vendeu em quantidade
-- e o valor total gerado. Em empate de quantidade, desempata pelo maior valor.
--
-- Estratégia: CTE calcula quantidade e valor por (afiliado, produto). Window
-- function ROW_NUMBER() com PARTITION BY affiliate_id e ORDER BY qty DESC,
-- total_value DESC garante um único resultado por afiliado sem subquery no WHERE.
-- =============================================================================
WITH affiliate_product_sales AS (
    SELECT
        o.affiliate_id,
        oi.product_id,
        p.title AS product_name,
        SUM(oi.quantity) AS total_quantity,
        ROUND(SUM(oi.quantity * oi.price), 2) AS total_value
    FROM orders o
    INNER JOIN order_items oi ON oi.order_id = o.id
    INNER JOIN products p ON p.id = oi.product_id
    WHERE o.deleted_at IS NULL
    GROUP BY o.affiliate_id, oi.product_id, p.title
),
ranked_products AS (
    SELECT
        aps.*,
        ROW_NUMBER() OVER (
            PARTITION BY affiliate_id
            ORDER BY total_quantity DESC, total_value DESC
        ) AS rn
    FROM affiliate_product_sales aps
)
SELECT
    rp.affiliate_id,
    a.name AS affiliate_name,
    rp.product_id,
    rp.product_name,
    rp.total_quantity AS units_sold,
    rp.total_value AS total_revenue
FROM ranked_products rp
INNER JOIN affiliates a ON a.id = rp.affiliate_id
WHERE rp.rn = 1
ORDER BY rp.total_quantity DESC;


-- =============================================================================
-- Query E — Performance e otimização
--
-- PROBLEMA DA QUERY ORIGINAL:
-- 1. `IN (SELECT ...)` no MySQL pode não ser otimizado como JOIN e força uma
--    varredura da subquery para cada linha de orders (N+1 no nível SQL).
-- 2. `DATE(o.created_at) >= '2024-01-01'` aplica função na coluna, tornando
--    o índice em created_at inutilizável (full table scan em 500k rows).
-- 3. A subquery correlacionada `(SELECT SUM(...) FROM order_items WHERE
--    order_id = o.id)` é executada UMA VEZ POR LINHA de orders — extremamente
--    custosa com volume alto.
-- 4. `SELECT *` retorna todas as colunas, incluindo blobs/textos desnecessários
--    e impede index-only scans.
--
-- SOLUÇÃO:
-- 1. Substituir IN+subquery por INNER JOIN — o otimizador usa hash join / merge join.
-- 2. Reescrever condição de data sem função: `o.created_at >= '2024-01-01'`
--    permite uso do índice composto (affiliate_id, created_at).
-- 3. Pré-agregar order_items em CTE e fazer JOIN — uma passagem em vez de N.
-- 4. Selecionar apenas colunas necessárias.
-- 5. Índice sugerido: CREATE INDEX idx_orders_affiliate_created ON orders(affiliate_id, created_at)
--    e CREATE INDEX idx_order_items_order ON order_items(order_id) para cobrir o JOIN.
-- =============================================================================

-- Query original problemática (mantida para referência):
-- SELECT *
-- FROM orders o
-- WHERE o.affiliate_id IN (
--     SELECT id FROM affiliates WHERE status = 'active'
-- )
-- AND DATE(o.created_at) >= '2024-01-01'
-- AND (
--     SELECT SUM(oi.quantity * oi.price)
--     FROM order_items oi
--     WHERE oi.order_id = o.id
-- ) > 100
-- ORDER BY o.created_at DESC;

-- Query otimizada:
WITH order_totals AS (
    SELECT
        order_id,
        SUM(quantity * price) AS total_value
    FROM order_items
    GROUP BY order_id
    HAVING SUM(quantity * price) > 100
)
SELECT
    o.id,
    o.affiliate_id,
    o.status,
    o.created_at,
    ot.total_value
FROM orders o
INNER JOIN affiliates a
    ON a.id = o.affiliate_id
    AND a.status = 'active'
INNER JOIN order_totals ot
    ON ot.order_id = o.id
WHERE o.created_at >= '2024-01-01'
  AND o.deleted_at IS NULL
ORDER BY o.created_at DESC;

-- Índices recomendados para suportar esta query em 500k pedidos:
-- CREATE INDEX idx_orders_affiliate_created ON orders (affiliate_id, created_at, deleted_at);
-- CREATE INDEX idx_affiliates_status ON affiliates (status, id);
-- CREATE INDEX idx_order_items_order_id ON order_items (order_id);
