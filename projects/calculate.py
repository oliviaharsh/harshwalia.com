#!/usr/bin/env python3
import sys
import json
import math

def compute_sip(years, monthly_start, annual_return, stepup):
    monthly_rate = annual_return / 12
    corpus = 0
    total_invested = 0
    current_sip = monthly_start
    yearly_data = []
    for y in range(1, years + 1):
        for m in range(12):
            corpus = corpus * (1 + monthly_rate) + current_sip
            total_invested += current_sip
        yearly_data.append({
            'year': y,
            'corpus': round(corpus, 2),
            'invested': round(total_invested, 2)
        })
        current_sip *= (1 + stepup)
    return corpus, total_invested, yearly_data, current_sip / (1 + stepup)

def required_corpus(monthly_exp_today, years, inflation):
    return monthly_exp_today * ((1 + inflation) ** years) * 12 * 25

def handle_free(d):
    age         = d.get('age', 25)
    retire_age  = d.get('retireAge', 60)
    sip         = d.get('sip', 25000)
    annual_ret  = d.get('annualReturn', 12) / 100
    stepup      = d.get('stepup', 10) / 100
    inflation   = d.get('inflation', 6) / 100
    expenses    = d.get('expenses', 50000)

    years = max(1, retire_age - age)
    corpus, invested, yearly_data, last_sip = compute_sip(years, sip, annual_ret, stepup)

    inflation_factor = (1 + inflation) ** years
    real_corpus      = corpus / inflation_factor
    exp_at_retire    = expenses * inflation_factor
    req              = exp_at_retire * 12 * 25
    gains            = corpus - invested

    return {
        'corpus':         round(corpus, 2),
        'totalInvested':  round(invested, 2),
        'gains':          round(gains, 2),
        'realCorpus':     round(real_corpus, 2),
        'expAtRetire':    round(exp_at_retire, 2),
        'requiredCorpus': round(req, 2),
        'surplus':        round(corpus - req, 2),
        'yearlyData':     yearly_data,
        'years':          years,
        'lastSIP':        round(last_sip, 2)
    }

def handle_when(d):
    age       = d.get('age', 25)
    sip       = d.get('sip', 25000)
    stepup    = d.get('stepup', 10) / 100
    annual_ret= d.get('annualReturn', 12) / 100
    inflation = d.get('inflation', 6) / 100
    expenses  = d.get('expenses', 50000)

    monthly_rate = annual_ret / 12
    corpus = 0
    total_invested = 0
    current_sip = sip
    yearly_data = []
    retire_age = None
    req = 0
    exp_at_retire = 0

    for years in range(1, 61):
        for m in range(12):
            corpus = corpus * (1 + monthly_rate) + current_sip
            total_invested += current_sip
        yearly_data.append({
            'year': years,
            'corpus': round(corpus, 2),
            'invested': round(total_invested, 2)
        })
        current_sip *= (1 + stepup)

        inflation_factor = (1 + inflation) ** years
        exp_at_retire    = expenses * inflation_factor
        req              = exp_at_retire * 12 * 25

        if corpus >= req and retire_age is None:
            retire_age = age + years
            break

    if retire_age is None:
        return {'error': 'Cannot retire within 60 years', 'yearlyData': yearly_data}

    return {
        'retireAge':      retire_age,
        'corpus':         round(corpus, 2),
        'totalInvested':  round(total_invested, 2),
        'gains':          round(corpus - total_invested, 2),
        'requiredCorpus': round(req, 2),
        'expAtRetire':    round(exp_at_retire, 2),
        'realCorpus':     round(corpus / ((1 + inflation) ** (retire_age - age)), 2),
        'surplus':        round(corpus - req, 2),
        'yearlyData':     yearly_data,
        'years':          retire_age - age
    }

def handle_howmuch(d):
    age        = d.get('age', 25)
    retire_age = d.get('retireAge', 60)
    expenses   = d.get('expenses', 50000)
    annual_ret = d.get('annualReturn', 12) / 100
    stepup     = d.get('stepup', 10) / 100
    inflation  = d.get('inflation', 6) / 100

    years            = max(1, retire_age - age)
    inflation_factor = (1 + inflation) ** years
    exp_at_retire    = expenses * inflation_factor
    req              = exp_at_retire * 12 * 25
    monthly_rate     = annual_ret / 12

    lo, hi = 1, 100000000
    sip_needed = hi
    for _ in range(80):
        mid = (lo + hi) / 2
        corpus = 0
        current_sip = mid
        for y in range(years):
            for m in range(12):
                corpus = corpus * (1 + monthly_rate) + current_sip
            current_sip *= (1 + stepup)
        if corpus >= req:
            sip_needed = mid
            hi = mid
        else:
            lo = mid

    corpus, invested, yearly_data, _ = compute_sip(years, sip_needed, annual_ret, stepup)

    return {
        'sipNeeded':      round(sip_needed, 2),
        'corpus':         round(corpus, 2),
        'totalInvested':  round(invested, 2),
        'gains':          round(corpus - invested, 2),
        'requiredCorpus': round(req, 2),
        'expAtRetire':    round(exp_at_retire, 2),
        'realCorpus':     round(corpus / inflation_factor, 2),
        'surplus':        round(corpus - req, 2),
        'yearlyData':     yearly_data,
        'years':          years
    }

def main():
    try:
        raw = sys.stdin.read()
        data = json.loads(raw)
        mode = data.get('mode', 'free')

        if mode == 'free':
            result = handle_free(data)
        elif mode == 'when':
            result = handle_when(data)
        elif mode == 'howmuch':
            result = handle_howmuch(data)
        else:
            result = {'error': 'Unknown mode'}

        print(json.dumps(result))
    except Exception as e:
        print(json.dumps({'error': str(e)}))

if __name__ == '__main__':
    main()
